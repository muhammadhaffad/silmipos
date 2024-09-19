create table toko_griyanaura.ms_produkpersediaandetail (
	id_persediaandetail serial primary key,
	id_persediaan int references toko_griyanaura.ms_produkpersediaan(id_persediaan) on update cascade on delete restrict,
	tanggal timestamp(0) default current_timestamp(0),
	keterangan varchar(200),
	stok_in numeric(10,2),
	stok_out numeric(10,2),
	hargajual int,
	hargabeli int
);
