alter table toko_griyanaura.tr_jurnal add column ref_id varchar(50);
alter table toko_griyanaura.ms_produkpersediaandetail add column ref_id varchar(50);

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelian()
  RETURNS trigger AS
$BODY$
declare
	_tanggalvalidmax timestamp(0);
	_tanggalorder timestamp(0);
begin
	_tanggalorder := (select tanggal from toko_griyanaura.tr_pembelian where id_pembelian = NEW.pembelian_parent);
	if (NEW.tanggal < _tanggalorder) then
		raise exception 'tanggal tidak boleh sebelum tanggal order pembelian';
	end if;
	
	select tanggal into _tanggalvalidmax from (
		select tanggal from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianinvoice = NEW.id_pembelian and tanggal >= OLD.tanggal
		union 
		select tanggal from toko_griyanaura.tr_pembelianretur where id_pembelian = NEW.id_pembelian and tanggal >= OLD.tanggal
	) as x order by tanggal asc limit 1;
	
	if (exists(select _tanggalvalidmax) and NEW.tanggal > _tanggalvalidmax) then
		raise exception 'tanggal harus kurang dari atau sama dengan %, karena terdapat transaksi sebelumnya', _tanggalvalidmax;
	end if;
	return NEW;
end;
$BODY$
	LANGUAGE plpgsql;

CREATE TRIGGER tb_cekupdatetanggalpembelian
	BEFORE UPDATE
	ON toko_griyanaura.tr_pembelian
	FOR EACH ROW
	EXECUTE PROCEDURE toko_griyanaura.ftb_cekupdatetanggalpembelian();


CREATE OR REPLACE FUNCTION toko_griyanaura.fta_updatetanggaltransaksi()
  RETURNS trigger AS
$BODY$
begin
	update toko_griyanaura.tr_jurnal set tanggal = NEW.tanggal where id_transaksi = NEW.id_transaksi;
	return NULL;
end;
$BODY$
	LANGUAGE plpgsql;

CREATE TRIGGER ta_updatetanggaltransaksi
	AFTER UPDATE
	ON toko_griyanaura.tr_transaksi
	FOR EACH ROW
	EXECUTE PROCEDURE toko_griyanaura.fta_updatetanggaltransaksi();

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cektanggalpembelian()
  RETURNS trigger AS
$BODY$
declare
	_tanggalorder timestamp(0);
begin
	_tanggalorder := (select tanggal from toko_griyanaura.tr_pembelian where id_pembelian = NEW.pembelian_parent);
	if (NEW.tanggal < _tanggalorder) then
		raise exception 'tanggal tidak boleh sebelum tanggal order pembelian';
	end if;
	
	return NEW;
end;
$BODY$
	LANGUAGE plpgsql;

CREATE TRIGGER tb_cektanggalpembelian
	BEFORE INSERT
	ON toko_griyanaura.tr_pembelian
	FOR EACH ROW
	EXECUTE PROCEDURE toko_griyanaura.ftb_cektanggalpembelian();