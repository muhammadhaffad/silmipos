<div class="print-only">
    @vite('resources/css/app.css')
    <style>
        #receipt > * {
            font-family: "OCR-B"; 
            font-size: .6rem;
        }
    </style>
    <div id="receipt" class="space-y-3" style="width: 8cm;">
        <table style="width: 100%; text-align:center">
            <tr>
                <td>GRIYA NAURA</td>
            </tr>
            <tr>
                <td>SUGIO LAMONGAN</td>
            </tr>
            <tr>
                <td>Telp. 123123123</td>
            </tr>
        </table>
        <table style="width: 100%; margin-top: 4px;">
            <tr>
                <td style="white-space: nowrap; width:50%;">NO. TRX:<br>#{{$data['transaksi_no']}}</td>
                <td style="white-space: nowrap; width:50%;">TANGGAL:<br><span style="text-align: end; display:block;">{{date('d/M/Y H:i', strtotime($data['tanggal']))}}</span></td>
            </tr>
            <tr>
                <td style="white-space: nowrap; width:50%;">KASIR:<br>ADMIN{{-- TODO: kasih column siapa yang input trx di table pembelian dan penjualan --}}</td>
                <td style="white-space: nowrap; width:50%;">PELANGGAN:<br>{{$data['kontak']['nama']}}{{-- TODO: kasih field nama di table penjualan dan pembelian --}}</td>
            </tr>
        </table>
        <table style="width: 100%; border-top: dashed; border-bottom: dashed;">
            @forelse ($data['penjualan_detail'] as $item)        
                <tr>
                    <td style="width: 50%">{{number($item['qty'])}}x {{$item['produk_varian']['varian']}}</td>
                    <td style="width: 50%; text-align:end;">Rp{{number_format($item['total'], 0, ',', '.')}}</td>
                </tr>
            @empty
                <tr>
                    <td style="width: 100%">Tidak ada item</td>
                </tr>
            @endforelse
        </table>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%">TOTAL</td>
                <td style="width: 50%; text-align: end">Rp{{number_format($data['totalraw'], 0, ',', '.')}}</td>
            </tr>
            <tr>
                <td style="width: 50%">DISKON</td>
                <td style="width: 50%; text-align: end">{{number_format($data['diskon'] ?: 0, 0, ',', '.')}}%</td>
            </tr>
            <tr>
                <td style="width: 50%; white-space: nowrap">GRAND TOTAL</td>
                <td style="width: 50%; text-align: end">Rp{{number_format($data['grandtotal'], 0, ',', '.')}}</td>
            </tr>
            <tr>
                <td style="width: 50%; white-space: nowrap">CASH</td>
                <td style="width: 50%; text-align: end">Rp{{number_format($data['nominalbayar'] + $data['kembalian'], 0, ',', '.')}}</td>
            </tr>
            <tr>
                <td style="width: 50%; white-space: nowrap">KEMBALI</td>
                <td style="width: 50%; text-align: end">Rp{{number_format($data['kembalian'], 0, ',', '.')}}</td>
            </tr>
        </table>
        <p style="text-align: center">
            TERIMA KASIH ATAS KUNJUNGAN ANDA<br>BARANG YANG SUDAH DIBELI TIDAK DAPAT DIKEMBALIKAN
        </p>
    </div>
    <script>
        window.onafterprint = function(event) { window.close() };
    </script>
</div>