<?php
namespace App\Services\Core\Jurnal;

use App\Exceptions\JurnalException;
use App\Models\Jurnal;
use App\Models\Transaksi;
use Exception;
use Illuminate\Support\Facades\Validator;

Trait JurnalService {
    public function entryJurnal(int $idTransaksi, array $detailTransaksi) {
        $validator = Validator::make($detailTransaksi, [
            '*.kode_akun' => 'required|string',
            '*.keterangan' => 'nullable|string',
            '*.nominaldebit' => 'required|numeric',
            '*.nominalkredit' => 'required|numeric'
        ]);
        $validator->validate();
        $transaksi = Transaksi::find($idTransaksi);
        $totalDebit = 0;
        $totalKredit = 0;
        foreach ($detailTransaksi as &$tr) {
            $tr['id_transaksi'] = $transaksi['id_transaksi'];
            $tr['tanggal'] = $transaksi['tanggal'];
            if (!isset($tr['ref_id'])) {
                $tr['ref_id'] = null;
            }
            $totalDebit += $tr['nominaldebit'];
            $totalKredit += $tr['nominalkredit'];
        }
        unset($tr);
        if ($totalDebit != $totalKredit) {
            throw new JurnalException('Total debit dan kredit tidak sama.');
        }
        Jurnal::query()->insert($detailTransaksi);
    }
}