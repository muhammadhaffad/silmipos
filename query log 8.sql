insert into toko_griyanaura.ms_akun (kode_akun, nama, is_aktif, id_akunjenis) values 
	('4006', 'Pendapatan Penyesuaian Persediaan', true, 'income'),
	('6202', 'Beban Penyesuaian Persediaan', true, 'expense');

alter table toko_griyanaura.ms_produkpersediaandetail 
	add column inserted_at timestamp(0) without time zone DEFAULT now(),
	add column inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	add column updated_at timestamp(0) without time zone DEFAULT now(),
	add column updated_by character varying(50) DEFAULT 'postgres'::character varying

alter table toko_griyanaura.tr_pindahgudang 
	add column id_transaksi integer references toko_griyanaura.tr_transaksi(id_transaksi) on update cascade on delete restrict;

with cte as (
	select tr.* from toko_griyanaura.tr_transaksi tr join toko_griyanaura.tr_pindahgudang using (transaksi_no)
)
update toko_griyanaura.tr_pindahgudang 
set id_transaksi = cte.id_transaksi
from cte
where toko_griyanaura.tr_pindahgudang.transaksi_no = cte.transaksi_no;

alter table toko_griyanaura.tr_pindahgudang 
	add column is_batal boolean default false;

alter table toko_griyanaura.tr_pindahgudangdetail
	add column harga_modal_dari_gudang integer,
	add column harga_modal_ke_gudang integer; 