<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSqlTransferService
{
    private const TABLES = ['branches', 'products', 'product_categories', 'product_variants', 'raw_materials', 'suppliers', 'customers', 'store_settings', 'expenses', 'cashier_shifts', 'pos_sales', 'pos_sale_items', 'branch_inventories', 'stock_movements', 'purchase_orders'];

    public function export(int $ownerId): string
    {
        $branchIds = Branch::withoutGlobalScopes()->where('user_id', $ownerId)->pluck('id');
        $pdo = DB::connection()->getPdo();
        $sql = "-- MAVA POS TENANT EXPORT v1\n-- tenant_owner_id: {$ownerId}\nSET FOREIGN_KEY_CHECKS=0;\n";
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) continue;
            $columns = Schema::getColumnListing($table);
            $query = DB::table($table);
            if (in_array('user_id', $columns, true)) $query->where('user_id', $ownerId);
            elseif (in_array('branch_id', $columns, true)) $query->whereIn('branch_id', $branchIds);
            else continue;
            foreach ($query->get() as $row) {
                $values = collect($columns)->map(fn (string $column) => $row->{$column} === null ? 'NULL' : $pdo->quote((string) $row->{$column}))->implode(', ');
                $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`, `', $columns).'`) VALUES ('.$values.');'."\n";
            }
        }
        return $sql."SET FOREIGN_KEY_CHECKS=1;\n";
    }

    public function import(string $sql, int $ownerId): int
    {
        if (! str_starts_with(trim($sql), '-- MAVA POS TENANT EXPORT v1')) throw new \InvalidArgumentException('File bukan export SQL MAVA yang valid.');
        if (! preg_match('/^-- tenant_owner_id:\s*(\d+)/mi', $sql, $header) || (int) $header[1] !== $ownerId) throw new \InvalidArgumentException('File SQL berasal dari tenant berbeda. Export dan import harus dilakukan pada tenant yang sama.');
        $statements = $this->splitStatements($sql);
        $count = 0;
        DB::transaction(function () use ($statements, $ownerId, &$count): void {
            foreach ($statements as $statement) {
                if (! preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)`/i', trim($statement), $match) || ! in_array($match[1], self::TABLES, true)) continue;
                if (! preg_match('/`user_id`\s*\)\s*VALUES\s*\([^)]*/i', $statement) && ! str_contains($statement, '`branch_id`')) continue;
                $driver = DB::connection()->getDriverName();
                $prefix = $driver === 'sqlite' ? 'INSERT OR IGNORE' : ($driver === 'pgsql' ? 'INSERT' : 'INSERT IGNORE');
                $statement = preg_replace('/^INSERT INTO/i', $prefix, trim($statement));
                if ($driver === 'pgsql') $statement .= ' ON CONFLICT DO NOTHING';
                DB::unprepared($statement);
                $count++;
            }
        });
        return $count;
    }

    private function splitStatements(string $sql): array
    {
        $result = []; $buffer = ''; $quoted = false; $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i]; $buffer .= $char;
            if ($char === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) $quoted = ! $quoted;
            if ($char === ';' && ! $quoted) { $result[] = trim($buffer); $buffer = ''; }
        }
        return $result;
    }
}
