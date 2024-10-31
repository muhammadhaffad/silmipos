alter table toko_griyanaura.tr_jurnal 
	add column inserted_at timestamp default current_timestamp(0),
	add column updated_at timestamp default current_timestamp(0);