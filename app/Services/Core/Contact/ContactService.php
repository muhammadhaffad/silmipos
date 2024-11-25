<?php
namespace App\Services\Core\Contact;

use App\Models\Kontak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContactService 
{
    public function storeContact($request) {
        $validator = Validator::make($request, [
            'jenis_kontak' => 'required|in:supplier,customer',
            'nama' => 'required|string',
            'alamat' => 'nullable|string',
            'nohp' => 'nullable|string',
            'gender' => 'nullable|in:LK,PR'
        ]);
        $validator->validate();
        DB::beginTransaction();
        try {
            if ($request['jenis_kontak'] == 'supplier') 
                $kodeKontak = DB::select("select ('SUPL' || lpad(nextval('toko_griyanaura.ms_kontak_supplier_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            else if ($request['jenis_kontak'] == 'customer') 
                $kodeKontak = DB::select("select ('CUST' || lpad(nextval('toko_griyanaura.ms_kontak_customer_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            else
                $kodeKontak = null;

            $kontak = Kontak::create([
                'kode_kontak' => $kodeKontak,
                'jenis_kontak' =>  $request['jenis_kontak'],
                'nama' => $request['nama'],
                'alamat' => $request['alamat'],
                'nohp' => $request['nohp'],
                'gender' =>  $request['gender']
            ]);
            DB::commit();
            return $kontak;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updateContact($id, $request) 
    {
        $validator = Validator::make($request, [
            'nama' => 'required|string',
            'alamat' => 'nullable|string',
            'nohp' => 'nullable|string',
            'gender' => 'nullable|in:LK,PR'
        ]);
        $validator->validate();
        DB::beginTransaction();
        try {
            $kontak = Kontak::find($id);
            $kontak->nama = $request['nama'];
            $kontak->alamat = $request['alamat'];
            $kontak->nohp = $request['nohp'];
            $kontak->gender = $request['gender'];
            $kontak->save();
            DB::commit();
            return $kontak;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deleteContact($id) 
    {
        DB::beginTransaction();
        try {
            $kontak = Kontak::find($id);
            $kontak->delete();
            DB::commit();
            return $kontak;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}