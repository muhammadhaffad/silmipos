alter table toko_griyanaura.tr_pindahgudangdetail 
	add unique (id_pindahgudang, kode_produkvarian);

alter table toko_griyanaura.tr_pindahgudang 
	add column is_valid boolean default false;