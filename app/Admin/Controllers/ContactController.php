<?php
namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\Delete;
use App\Admin\Actions\Grid\Edit;
use App\Admin\Actions\Grid\Show;
use App\Exceptions\PurchasePaymentException;
use App\Models\Kontak;
use App\Services\Core\Contact\ContactService;
use App\Services\Core\Purchase\PurchaseRefundPaymentService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Displayers\DropdownActions;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactController extends AdminController
{
    protected $contactService;
    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
    }

    public function listKontakGrid() {
        $grid = new Grid(new Kontak);
        if (!isset($_GET['_sort']['column']) and empty($_GET['sort']['column'])) {
            $grid->model()->orderByRaw('id_kontak desc');
        }
        $grid->column('kode_kontak', 'Kode kontak')->sortable();
        $grid->column('nama', 'Nama')->sortable();
        $grid->column('jenis_kontak', 'Jenis')->label()->sortable();
        $grid->column('nohp', 'No. Hp');
        $grid->column('alamat', 'Alamat');
        $grid->column('inserted_at', 'Tanggal daftar')->display(function ($val) {
            return \date('d F Y', \strtotime($val));
        })->sortable();
        $grid->actions(function (DropdownActions $actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
            $actions->add(new Show);
            $actions->add(new Edit);
            // dump($this);
            $actions->add(new Delete(route(admin_get_route('contact.delete'), $this->row->id_kontak)));
        });
        return $grid;
    }
    public function listKontak(Content $content) {
        return $content
            ->title('Kontak')
            ->description('Daftar')
            ->row(function (Row $row) {
                $row->column(12, function (Column $column) {
                    $column->row($this->listKontakGrid());
                });
            });
    }

    public function createKontakForm($model)
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('contact.store')));
        $form->text('kode_kontak', 'Kode kontak')->placeholder('[AUTO]')->width('30%')->disable();
        $form->select('jenis_kontak', 'Jenis kontak')->options([
            'supplier' => 'Supplier',
            'customer' => 'Customer'
        ])->default('customer')->required();
        $form->text('nama', 'Nama kontak')->autofocus()->required();
        $form->textarea('alamat', 'Alamat');
        $form->text('nohp', 'No. HP')->required();
        $form->select('gender', 'Jenis kelamin')->options([
            'LK' => 'Laki-laki',
            'PR' => 'Perempuan'
        ])->default('LK')->required();
        return $form;
    }
    public function editKontakForm($idKontak, $model)
    {
        $form = new Form($model);
        $data = $form->model()->findOrFail($idKontak);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('contact.update'), ['idKontak' => $idKontak]));
        $form->tools(function (Tools $tools) use ($idKontak, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('contact.delete'), ['idKontak' => $idKontak]), listPath: route(admin_get_route('contact.create'))));
            $tools->append($tools->renderView(route(admin_get_route('contact.detail'), ['idKontak' => $idKontak])));
            $tools->append($tools->renderList(route(admin_get_route('contact.list'))));
        });
        $form->text('kode_kontak', 'Kode kontak')->placeholder('[AUTO]')->width('30%')->disable()->value($data['kode_kontak']);
        $form->select('jenis_kontak', 'Jenis kontak')->options([
            'supplier' => 'Supplier',
            'customer' => 'Customer'
        ])->value($data['jenis_kontak'])->required();
        $form->text('nama', 'Nama kontak')->autofocus()->value($data['nama'])->required();
        $form->textarea('alamat', 'Alamat')->value($data['alamat']);
        $form->text('nohp', 'No. HP')->required()->value($data['nohp']);
        $form->select('gender', 'Jenis kelamin')->options([
            'LK' => 'Laki-laki',
            'PR' => 'Perempuan'
        ])->required()->value($data['gender']);
        $form->disableEditingCheck();
        $form->disableCreatingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function detailKontakForm($idKontak, $model)
    {
        $form = new Form($model);
        $data = $form->model()->findOrFail($idKontak);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('contact.update'), ['idKontak' => $idKontak]));
        $form->tools(function (Tools $tools) use ($idKontak, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('contact.delete'), ['idKontak' => $idKontak]), listPath: route(admin_get_route('contact.create'))));
            $tools->append($tools->renderEdit(route(admin_get_route('contact.edit'), ['idKontak' => $idKontak])));
            $tools->append($tools->renderList(route(admin_get_route('contact.list'))));
        });
        $form->text('kode_kontak', 'Kode kontak')->value($data['kode_kontak'])->placeholder('[AUTO]')->width('30%')->disable();
        $form->select('jenis_kontak', 'Jenis kontak')->options([
            'supplier' => 'Supplier',
            'customer' => 'Customer'
        ])->disable()->value($data['jenis_kontak']);
        $form->text('nama', 'Nama kontak')->autofocus()->disable()->value($data['nama']);
        $form->textarea('alamat', 'Alamat')->disable()->value($data['alamat']);
        $form->text('nohp', 'No. HP')->disable()->value($data['nohp']);
        $form->select('gender', 'Jenis kelamin')->options([
            'LK' => 'Laki-laki',
            'PR' => 'Perempuan'
        ])->disable()->value($data['gender']);
        $form->disableReset();
        $form->disableSubmit();
        return $form;
    }

    public function createKontak(Content $content)
    {
        return $content
            ->title('Kontak')
            ->description('Buat')
            ->body($this->createKontakForm(new Kontak()));
    }
    public function editKontak(Content $content, $idKontak)
    {
        return $content
            ->title('Kontak')
            ->description('Ubah')
            ->body($this->editKontakForm($idKontak, new Kontak()));
    }
    public function detailKontak(Content $content, $idKontak)
    {
        return $content
            ->title('Refund Pembelian Pembayaran')
            ->description('Ubah')
            ->body($this->detailKontakForm($idKontak, new Kontak()));
    }

    public function storeKontak(Request $request)
    {
        try {
            $result = $this->contactService->storeContact($request->all());
            admin_toastr('Sukses tambah kontak');
            return redirect()->route(admin_get_route('contact.edit'), ['idKontak' => $result->id_kontak]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchasePaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function updateKontak($idKontak, Request $request)
    {
        try {
            $result = $this->contactService->updateContact($idKontak, $request->all());
            admin_toastr('Sukses update kontak');
            // return redirect()->route(admin_get_route('purchase.return.edit'), ['idRetur' => $result->id_pembelianretur]);
            return redirect()->back();
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchasePaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function deleteKontak(Request $request, $idKontak) 
    {
        try {
            $this->contactService->deleteContact($idKontak);
            admin_toastr('Sukses hapus kontak');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus kontak'
            ];
        } catch (PurchasePaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return [
                'status' => false,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}