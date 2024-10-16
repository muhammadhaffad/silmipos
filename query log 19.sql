alter table toko_griyanaura.tr_pembelianrefund add column id_kontak integer references toko_griyanaura.ms_kontak(id_kontak) on update cascade on delete restrict;
alter table toko_griyanaura.tr_pembelianrefund add column id_transaksi integer references toko_griyanaura.tr_transaksi(id_transaksi) on update cascade on delete restrict;
