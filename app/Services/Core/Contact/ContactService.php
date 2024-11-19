<?php
namespace App\Services\Core\Contact;

use Illuminate\Support\Facades\Validator;

class ContactService 
{
    public function storeContact($request) {
        $validator = Validator::make($request, [
            'jenis_kontak' => 'required|in:supplier,customer',
            'nama' => 'required|string',
            'alamat' => 'required|string',
            'nohp' => 'required|string',
            'gender' => 'required|in:LK,PR'
        ]);
        $validator->validate();
    }
    public function updateContact($id, $request) {}
    public function deleteContact($id) {}
}