alter table toko_griyanaura.lv_gudang add column default_varianharga int references toko_griyanaura.lv_varianharga(id_varianharga) on update cascade on delete restrict;

alter table toko_griyanaura.tr_pindahgudang add column is_valid boolean default false;

alter table toko_griyanaura.tr_pindahgudang drop column is_valid;

insert into toko_griyanaura.lv_transaksigrup (id_transaksigrup, nama) values ('persediaan', 'Persediaan');

insert into toko_griyanaura.lv_transaksijenis (id_transaksijenis, id_transaksigrup, nama) values ('persediaan_pindahgudang', 'persediaan', 'Pindah Gudang');
