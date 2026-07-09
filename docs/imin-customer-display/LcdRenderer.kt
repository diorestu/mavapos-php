package com.mavapos.customerdisplay

import android.content.Context
import com.imin.lcd.ILcdManager
import org.json.JSONArray
import org.json.JSONObject

class LcdRenderer(private val context: Context) {

    private var lcd: ILcdManager? = null
    private var rowCount: Int = 4

    fun init() {
        lcd = ILcdManager.getInstance(context)
        lcd?.sendLCDCommand(1)
        lcd?.setTextSize(55)
    }

    fun shutdown() {
        lcd?.sendLCDCommand(4)
    }

    fun render(state: JSONObject) {
        val mode = state.optString("mode", "cart")
        when (mode) {
            "checkout" -> renderCheckout(state)
            else -> renderCart(state)
        }
    }

    private fun renderCart(state: JSONObject) {
        val total = formatRupiahShort(state.optInt("total"))
        val cart = state.optJSONArray("cart") ?: JSONArray()

        val lines = mutableListOf<String>()
        val aligns = mutableListOf<Int>()

        val max = minOf(cart.length(), rowCount - 1)
        for (i in 0 until max) {
            val item = cart.getJSONObject(i)
            val name = shorten(item.optString("name"), 6)
            val qty = item.optInt("quantity")
            val lineTotal = formatRupiahShort(item.optInt("line_total"))
            lines.add("${qty}x$name")
            aligns.add(0)
            lines.add(lineTotal)
            aligns.add(2)
        }

        for (i in lines.size until (rowCount - 1) * 2) {
            lines.add("")
            aligns.add(1)
        }

        lines.add("TOTAL $total")
        aligns.add(2)

        lcd?.sendLCDMultiString(lines.toTypedArray(), aligns.toIntArray())
    }

    private fun renderCheckout(state: JSONObject) {
        val total = formatRupiahFull(state.optInt("total"))
        val change = state.optInt("change_amount", 0)
        val method = state.optString("payment_method", "cash")

        val lines = listOf("SELESAI", total, if (change > 0 && method == "cash") "KEMBALI ${formatRupiahFull(change)}" else "")
        val aligns = listOf(1, 1, 1)

        lcd?.sendLCDMultiString(lines.toTypedArray(), aligns.toIntArray())
    }

    private fun shorten(text: String, max: Int): String {
        val clean = text.uppercase().filter { it.isLetterOrDigit() }
        return if (clean.length <= max) clean else clean.substring(0, max)
    }

    private fun formatRupiahShort(value: Int): String {
        return when {
            value <= 0 -> "0"
            value < 1000 -> value.toString()
            value < 1_000_000 -> "${value / 1000}K"
            else -> "${value / 1_000_000}JT"
        }
    }

    private fun formatRupiahFull(value: Int): String {
        val formatted = String.format("%,d", value).replace(',', '.')
        return "Rp$formatted"
    }
}
