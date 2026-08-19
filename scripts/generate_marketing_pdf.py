from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, Image, HRFlowable
)
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.lib.utils import ImageReader
from pathlib import Path
from PIL import Image as PILImage

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "output/pdf/mavapos-marketing-tools-product-knowledge.pdf"
OUT.parent.mkdir(parents=True, exist_ok=True)

BLUE = colors.HexColor("#465fff")
BLUE_DARK = colors.HexColor("#1a2238")
BLUE_PALE = colors.HexColor("#ecf3ff")
INK = colors.HexColor("#101828")
MUTED = colors.HexColor("#667085")
LINE = colors.HexColor("#e4e7ec")
PALE = colors.HexColor("#f8faff")
GREEN = colors.HexColor("#039855")

try:
    pdfmetrics.registerFont(TTFont("Inter", "/System/Library/Fonts/Supplemental/Arial.ttf"))
    pdfmetrics.registerFont(TTFont("Inter-Bold", "/System/Library/Fonts/Supplemental/Arial Bold.ttf"))
    BODY, BOLD = "Inter", "Inter-Bold"
except Exception:
    BODY, BOLD = "Helvetica", "Helvetica-Bold"

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="CoverKicker", fontName=BOLD, fontSize=9, leading=12, textColor=BLUE, tracking=1.2))
styles.add(ParagraphStyle(name="CoverTitle", fontName=BOLD, fontSize=30, leading=34, textColor=INK, spaceAfter=12))
styles.add(ParagraphStyle(name="CoverSub", fontName=BODY, fontSize=13, leading=19, textColor=MUTED))
styles.add(ParagraphStyle(name="H1x", fontName=BOLD, fontSize=20, leading=24, textColor=INK, spaceAfter=8))
styles.add(ParagraphStyle(name="H2x", fontName=BOLD, fontSize=12, leading=16, textColor=INK, spaceBefore=4, spaceAfter=5))
styles.add(ParagraphStyle(name="Bodyx", fontName=BODY, fontSize=9.2, leading=13.5, textColor=INK))
styles.add(ParagraphStyle(name="Smallx", fontName=BODY, fontSize=8, leading=11, textColor=MUTED))
styles.add(ParagraphStyle(name="CardTitle", fontName=BOLD, fontSize=10, leading=13, textColor=BLUE_DARK))
styles.add(ParagraphStyle(name="Quote", fontName=BOLD, fontSize=15, leading=20, textColor=BLUE_DARK, alignment=TA_CENTER))
styles.add(ParagraphStyle(name="Tablex", fontName=BODY, fontSize=8.2, leading=11, textColor=INK))
styles.add(ParagraphStyle(name="TableBold", fontName=BOLD, fontSize=8.2, leading=11, textColor=INK))

def P(text, style="Bodyx"):
    return Paragraph(text, styles[style])

def bullets(items):
    return [P("<font color='#465fff'>•</font> " + x, "Bodyx") for x in items]

def card(title, body, width):
    content = [P(title, "CardTitle"), Spacer(1, 3), P(body, "Smallx")]
    t = Table([[content]], colWidths=[width])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0,0), (-1,-1), colors.white), ("BOX", (0,0), (-1,-1), 0.7, LINE),
        ("LEFTPADDING", (0,0), (-1,-1), 10), ("RIGHTPADDING", (0,0), (-1,-1), 10),
        ("TOPPADDING", (0,0), (-1,-1), 9), ("BOTTOMPADDING", (0,0), (-1,-1), 9),
    ]))
    return t

def header_footer(canvas, doc):
    canvas.saveState()
    w, h = A4
    if doc.page > 1:
        canvas.setFillColor(BLUE)
        canvas.rect(0, h-7*mm, w, 7*mm, fill=1, stroke=0)
        canvas.setFillColor(MUTED)
        canvas.setFont(BODY, 7.5)
        canvas.drawString(18*mm, 10*mm, "MavaPOS | Marketing Tools & Product Knowledge")
        canvas.drawRightString(w-18*mm, 10*mm, f"{doc.page:02d}")
    canvas.restoreState()

def build():
    w, h = A4
    doc = BaseDocTemplate(str(OUT), pagesize=A4, leftMargin=18*mm, rightMargin=18*mm, topMargin=18*mm, bottomMargin=17*mm)
    frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="normal")
    doc.addPageTemplates([PageTemplate(id="all", frames=frame, onPage=header_footer)])
    story = []
    screenshot = ROOT / "public/images/brand/hero-macbook-dashboard.webp"
    crop_dir = ROOT / "tmp/pdfs"
    crop_dir.mkdir(parents=True, exist_ok=True)
    with PILImage.open(screenshot) as source:
        source.crop((180, 70, 1800, 1080)).save(crop_dir / "mavapos-dashboard-crop.png")
        source.crop((220, 90, 1050, 870)).save(crop_dir / "mavapos-sidebar-crop.png")

    # 1 cover
    logo = ROOT / "public/logo.png"
    story += [Spacer(1, 15*mm), Image(str(logo), width=72*mm, height=12.7*mm), Spacer(1, 20*mm),
              P("SOLUSI KASIR DIGITAL", "CoverKicker"), Spacer(1, 5*mm),
              P("MavaPOS untuk bisnis yang ingin bergerak lebih cepat.", "CoverTitle"),
              P("Solusi kasir digital untuk membantu Anda mengelola transaksi, stok, pelanggan, cabang, dan laporan usaha dengan lebih rapi.", "CoverSub"), Spacer(1, 15*mm)]
    hero = Table([[P("<b>POS cloud untuk UMKM, F&B, retail, dan penyedia jasa</b><br/><br/>Satukan transaksi kasir, stok, resep, pelanggan, cabang, dan laporan finansial dalam satu ruang kerja online.", "Bodyx")]], colWidths=[doc.width])
    hero.setStyle(TableStyle([("BACKGROUND", (0,0), (-1,-1), BLUE_PALE), ("BOX", (0,0), (-1,-1), 0, BLUE_PALE), ("LEFTPADDING", (0,0), (-1,-1), 16), ("RIGHTPADDING", (0,0), (-1,-1), 16), ("TOPPADDING", (0,0), (-1,-1), 14), ("BOTTOMPADDING", (0,0), (-1,-1), 14)]))
    story += [hero, Spacer(1, 18*mm), P("<b>" + "Lebih sedikit rekap manual. Lebih banyak kendali atas operasional." + "</b>", "Quote"), Spacer(1, 17*mm), PageBreak()]

    # 2 positioning
    story += [P("01  POSITIONING & VALUE PROPOSITION", "CoverKicker"), P("Jual hasilnya, bukan sekadar daftar fitur.", "H1x"), P("MavaPOS membantu pemilik usaha melihat apa yang terjadi di toko secara lebih cepat: transaksi tercatat, stok terpantau, shift lebih tertib, dan laporan siap ditinjau.", "Bodyx"), Spacer(1, 8*mm)]
    cards = [[card("Untuk pemilik usaha", "Kontrol penjualan, stok, pengeluaran, cabang, dan kinerja tim dari dashboard.", 54*mm), card("Untuk kasir", "Alur kerja jelas: buka shift, layani transaksi, terima tunai/QRIS/kartu, lalu tutup shift dengan rekonsiliasi.", 54*mm), card("Untuk supervisor", "Data operasional dan laporan lebih rapi untuk mengambil keputusan tanpa menunggu rekap manual.", 54*mm)]]
    t = Table(cards, colWidths=[56*mm,56*mm,56*mm], hAlign="LEFT")
    t.setStyle(TableStyle([("VALIGN", (0,0), (-1,-1), "TOP"), ("LEFTPADDING", (0,0), (-1,-1), 0), ("RIGHTPADDING", (0,0), (-1,-1), 3)]))
    story += [t, Spacer(1, 9*mm), Table([[P("“MavaPOS adalah platform kasir online yang menyatukan transaksi, stok, resep, pelanggan, dan laporan usaha. Kasir bisa bekerja dengan alur shift yang jelas, sementara pemilik dapat memantau angka penting dan mengelola operasional dari satu dashboard. Jadi bisnis tidak lagi bergantung pada catatan manual yang tercecer.”", "Quote")]], colWidths=[doc.width], style=TableStyle([("BACKGROUND", (0,0), (-1,-1), PALE), ("LEFTPADDING", (0,0), (-1,-1), 14), ("RIGHTPADDING", (0,0), (-1,-1), 14), ("TOPPADDING", (0,0), (-1,-1), 15), ("BOTTOMPADDING", (0,0), (-1,-1), 15)])), Spacer(1, 8*mm), P("Cocok untuk", "H2x"), P("Kafe dan restoran • toko retail • kedai minuman • salon dan jasa • bisnis dengan satu atau beberapa cabang", "Bodyx"), PageBreak()]

    # 3 product knowledge
    story += [P("02  FITUR UTAMA", "CoverKicker"), P("Cara kerja MavaPOS dari depan sampai belakang.", "H1x"), Spacer(1, 3*mm)]
    feature_data = [
        [P("Modul", "TableBold"), P("Yang dilakukan", "TableBold"), P("Manfaat yang dijelaskan ke calon pengguna", "TableBold")],
        [P("Kasir / POS", "TableBold"), P("Cari produk, kategori, barcode, varian, keranjang, pembayaran tunai/QRIS/kartu, dan cetak struk.", "Tablex"), P("Transaksi lebih cepat dan pilihan pembayaran lebih fleksibel.", "Tablex")],
        [P("Produk & inventori", "TableBold"), P("Kelola kategori, SKU, harga modal/jual, stok minimum, varian, stok masuk/keluar, opname, dan transfer antar cabang.", "Tablex"), P("Stok lebih mudah dipantau dan risiko kehabisan barang lebih cepat terlihat.", "Tablex")],
        [P("Resep & bahan baku", "TableBold"), P("Hubungkan menu jualan dengan komposisi bahan baku; transaksi dapat mengurangi bahan secara otomatis.", "Tablex"), P("Sangat relevan untuk F&B yang ingin mengontrol pemakaian bahan dan konsistensi operasional.", "Tablex")],
        [P("Shift & tim", "TableBold"), P("Buka, ganti, dan tutup shift; catat kas awal, pendamping, checklist, dan ringkasan pembayaran.", "Tablex"), P("Serah-terima kas lebih tertib dan tanggung jawab transaksi lebih jelas.", "Tablex")],
        [P("Laporan", "TableBold"), P("Tinjau penjualan, jurnal, laba rugi, pengeluaran, stok menipis, dan unduh laporan.", "Tablex"), P("Pemilik dapat membuat keputusan berdasarkan data, bukan perkiraan.", "Tablex")],
        [P("Pelanggan & supplier", "TableBold"), P("Simpan data pelanggan saat checkout serta kelola supplier dan pembelian.", "Tablex"), P("Riwayat dan hubungan operasional lebih mudah dilacak.", "Tablex")],
    ]
    ft = Table(feature_data, colWidths=[29*mm, 72*mm, 67*mm], repeatRows=1)
    ft.setStyle(TableStyle([("BACKGROUND", (0,0), (-1,0), BLUE_PALE), ("GRID", (0,0), (-1,-1), 0.4, LINE), ("VALIGN", (0,0), (-1,-1), "TOP"), ("LEFTPADDING", (0,0), (-1,-1), 7), ("RIGHTPADDING", (0,0), (-1,-1), 7), ("TOPPADDING", (0,0), (-1,-1), 7), ("BOTTOMPADDING", (0,0), (-1,-1), 7)]))
    story += [ft, Spacer(1, 6*mm), P("Dashboard operasional", "H2x"), Image(str(screenshot), width=doc.width, height=doc.width*1080/2048), P("Dashboard menempatkan ringkasan toko, penjualan, pendapatan, dan stok menipis dalam satu tampilan.", "Smallx"), Spacer(1, 5*mm), P("Mulai dari dashboard, lanjutkan ke transaksi POS, lihat dampaknya pada stok dan resep, lalu tinjau shift serta laporan cabang.", "Bodyx"), PageBreak()]

    # 4 marketing tools
    story += [P("03  SOLUSI UNTUK OPERASIONAL BISNIS", "CoverKicker"), P("Satu sistem untuk operasional yang lebih rapi.", "H1x"), P("MavaPOS membantu mengubah pekerjaan harian yang tersebar menjadi alur kerja yang lebih terstruktur.", "Bodyx"), Spacer(1, 5*mm)]
    qdata = [[P("Tantangan bisnis", "TableBold"), P("Yang dibantu MavaPOS", "TableBold")], [P("Rekap penjualan harian memakan waktu.", "Tablex"), P("Dashboard dan laporan penjualan yang mudah ditinjau.", "Tablex")], [P("Sulit mengetahui stok yang menipis atau terpakai.", "Tablex"), P("Minimum stock, mutasi, dan resep bahan baku.", "Tablex")], [P("Operasional melibatkan beberapa kasir atau cabang.", "Tablex"), P("Role, shift, dan pengelolaan cabang.", "Tablex")], [P("Pembayaran tunai, QRIS, dan kartu perlu dicocokkan.", "Tablex"), P("Ringkasan shift dan laporan pembayaran.", "Tablex")]]
    qt=Table(qdata,colWidths=[83*mm,85*mm]); qt.setStyle(TableStyle([("BACKGROUND", (0,0), (-1,0), BLUE_PALE), ("GRID", (0,0), (-1,-1), 0.4, LINE), ("VALIGN", (0,0), (-1,-1), "TOP"), ("LEFTPADDING", (0,0), (-1,-1), 8), ("RIGHTPADDING", (0,0), (-1,-1), 8), ("TOPPADDING", (0,0), (-1,-1), 7), ("BOTTOMPADDING", (0,0), (-1,-1), 7)]))
    story += [qt, Spacer(1, 5*mm), P("Cuplikan antarmuka", "H2x"), Image(str(crop_dir / "mavapos-dashboard-crop.png"), width=doc.width, height=doc.width*1010/1620), Image(str(crop_dir / "mavapos-sidebar-crop.png"), width=doc.width, height=doc.width*780/830), P("Dashboard dan navigasi modul MavaPOS.", "Smallx"), Spacer(1, 5*mm), Table([[P("“Saya takut implementasinya rumit.”", "TableBold"), P("Mulai dari alur inti: produk → kasir → laporan. MavaPOS berbasis web, jadi dapat dibuka melalui browser di laptop, tablet, iPad, atau smartphone.", "Tablex")], [P("“Saya sudah punya catatan sendiri.”", "TableBold"), P("MavaPOS membantu memindahkan catatan ke alur yang lebih terstruktur sehingga transaksi, stok, dan laporan berada dalam satu sumber data.", "Tablex")], [P("“Internet kadang tidak stabil.”", "TableBold"), P("MavaPOS mendukung pencatatan transaksi saat koneksi tidak stabil, kemudian data dapat disinkronkan kembali saat koneksi tersambung.", "Tablex")]], colWidths=[48*mm,120*mm], style=TableStyle([("BACKGROUND", (0,0), (-1,-1), PALE), ("GRID", (0,0), (-1,-1), 0.4, LINE), ("VALIGN", (0,0), (-1,-1), "TOP"), ("LEFTPADDING", (0,0), (-1,-1), 8), ("RIGHTPADDING", (0,0), (-1,-1), 8), ("TOPPADDING", (0,0), (-1,-1), 7), ("BOTTOMPADDING", (0,0), (-1,-1), 7)])), Spacer(1, 7*mm), P("Jadwalkan demo 20 menit. Kita gunakan satu contoh transaksi bisnis Anda, lalu lihat bagaimana stok dan laporan berubah setelah transaksi selesai.", "Quote"), PageBreak()]

    # 5 plans and onboarding
    story += [P("04  PAKET & PERTANYAAN UMUM", "CoverKicker"), P("Pilih paket yang sesuai dengan tahap bisnis Anda.", "H1x"), Spacer(1, 3*mm)]
    plans = [[P("Paket", "TableBold"), P("Harga publik saat ini", "TableBold"), P("Cocok untuk", "TableBold")], [P("Starter", "TableBold"), P("Gratis / 14 hari", "Tablex"), P("Mencoba fitur inti, 1 outlet, maksimal 50 SKU.", "Tablex")], [P("Mava Basic", "TableBold"), P("Rp199.000 / outlet / bulan<br/><font color='#465fff'>Rp149.000 jika bayar tahunan</font>", "Tablex"), P("Outlet yang membutuhkan POS online dan laporan dasar.", "Tablex")], [P("Mava Pro", "TableBold"), P("Rp249.000 / outlet / bulan<br/><font color='#465fff'>Rp199.000 jika bayar tahunan</font>", "Tablex"), P("Bisnis aktif, retail, kafe, salon, dan usaha yang membutuhkan stok, resep, laporan lengkap, multi-akun, serta QRIS.", "Tablex")]]
    pt=Table(plans,colWidths=[32*mm,55*mm,81*mm]); pt.setStyle(TableStyle([("BACKGROUND", (0,0), (-1,0), BLUE_PALE), ("GRID", (0,0), (-1,-1), 0.4, LINE), ("VALIGN", (0,0), (-1,-1), "TOP"), ("LEFTPADDING", (0,0), (-1,-1), 8), ("RIGHTPADDING", (0,0), (-1,-1), 8), ("TOPPADDING", (0,0), (-1,-1), 7), ("BOTTOMPADDING", (0,0), (-1,-1), 7)]))
    story += [pt, Spacer(1, 7*mm), P("FAQ singkat untuk sales", "H2x")]
    faqs = [
        ("Perlu install aplikasi?", "Tidak perlu untuk penggunaan utama; MavaPOS berbasis web dan dibuka melalui browser."),
        ("Bisa cetak struk?", "Ya. Dukungan mencakup printer termal Bluetooth umum, USB, serta jaringan LAN/Wi-Fi sesuai konfigurasi perangkat."),
        ("Bagaimana pembayaran langganan?", "Billing tersedia melalui QRIS; status pembayaran diperbarui otomatis melalui sistem pembayaran dan pengecekan status."),
        ("Apa yang perlu disiapkan saat onboarding?", "Daftar produk, kategori, harga, stok awal, pengguna/role, cabang, supplier, dan bila F&B, komposisi resep bahan baku."),
    ]
    faq_rows=[]
    for q,a in faqs: faq_rows.append([P(q, "TableBold"), P(a, "Tablex")])
    faq=Table(faq_rows,colWidths=[52*mm,116*mm]); faq.setStyle(TableStyle([("ROWBACKGROUNDS", (0,0), (-1,-1), [colors.white, PALE]), ("LINEBELOW", (0,0), (-1,-1), 0.4, LINE), ("VALIGN", (0,0), (-1,-1), "TOP"), ("LEFTPADDING", (0,0), (-1,-1), 8), ("RIGHTPADDING", (0,0), (-1,-1), 8), ("TOPPADDING", (0,0), (-1,-1), 7), ("BOTTOMPADDING", (0,0), (-1,-1), 7)]))
    story += [faq, Spacer(1, 8*mm), Table([[P("Mulai dari satu outlet dan satu alur transaksi. Setelah data dasar rapi, perluas ke stok, resep, cabang, dan laporan.", "Bodyx")]], colWidths=[doc.width], style=TableStyle([("BACKGROUND", (0,0), (-1,-1), BLUE_PALE), ("LEFTPADDING", (0,0), (-1,-1), 10), ("RIGHTPADDING", (0,0), (-1,-1), 10), ("TOPPADDING", (0,0), (-1,-1), 10), ("BOTTOMPADDING", (0,0), (-1,-1), 10)]))]
    doc.build(story)

if __name__ == "__main__":
    build()
    print(OUT)
