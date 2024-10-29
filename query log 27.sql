alter table toko_griyanaura.tr_penjualanrefund 
	add column id_kontak int references toko_griyanaura.ms_kontak(id_kontak) on update cascade on delete restrict,
	add column id_transaksi int references toko_griyanaura.tr_transaksi(id_transaksi) on update cascade on delete restrict;