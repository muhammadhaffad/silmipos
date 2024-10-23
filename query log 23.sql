alter table toko_griyanaura.tr_pembelianreturdetail 
	add column id_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict;

with subQ as (
	select * from toko_griyanaura.tr_pembeliandetail
)
update toko_griyanaura.tr_pembelianreturdetail
set id_gudang = subQ.id_gudang
from subQ
where toko_griyanaura.tr_pembelianreturdetail.id_pembeliandetail = subQ.id_pembeliandetail;