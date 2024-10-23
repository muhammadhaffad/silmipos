alter table toko_griyanaura.tr_penjualan add column id_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict;

-- Function: toko_griyanaura.ftb_cektanggalpenjualan()

-- DROP FUNCTION toko_griyanaura.ftb_cektanggalpenjualan();

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cektanggalpenjualan()
  RETURNS trigger AS
$BODY$
declare
	_tanggalorder timestamp(0);
begin
	_tanggalorder := (select tanggal from toko_griyanaura.tr_penjualan where id_penjualan = NEW.penjualan_parent);
	if (NEW.tanggal < _tanggalorder) then
		raise exception 'tanggal tidak boleh sebelum tanggal order penjualan';
	end if;
	
	return NEW;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.ftb_cektanggalpenjualan()
  OWNER TO postgres;

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cekupdatetanggalpenjualan()
  RETURNS trigger AS
$BODY$
declare
	_tanggalvalidmax timestamp(0);
	_tanggalorder timestamp(0);
begin
	_tanggalorder := (select tanggal from toko_griyanaura.tr_penjualan where id_penjualan = NEW.penjualan_parent);
	if (NEW.tanggal < _tanggalorder) then
		raise exception 'tanggal tidak boleh sebelum tanggal order penjualan';
	end if;
	
	select tanggal into _tanggalvalidmax from (
		select tanggal from toko_griyanaura.tr_penjualanalokasipembayaran where id_penjualaninvoice = NEW.id_penjualan and tanggal >= OLD.tanggal
		union 
		select tanggal from toko_griyanaura.tr_penjualanretur where id_penjualan = NEW.id_penjualan and tanggal >= OLD.tanggal
	) as x order by tanggal asc limit 1;
	
	if (exists(select _tanggalvalidmax) and NEW.tanggal > _tanggalvalidmax) then
		raise exception 'tanggal harus kurang dari atau sama dengan %, karena terdapat transaksi sebelumnya', _tanggalvalidmax;
	end if;
	return NEW;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.ftb_cekupdatetanggalpenjualan()
  OWNER TO postgres;

-- Trigger: tb_cektanggalpenjualan on toko_griyanaura.tr_penjualan

-- DROP TRIGGER tb_cektanggalpenjualan ON toko_griyanaura.tr_penjualan;

CREATE TRIGGER tb_cektanggalpenjualan
  BEFORE INSERT
  ON toko_griyanaura.tr_penjualan
  FOR EACH ROW
  EXECUTE PROCEDURE toko_griyanaura.ftb_cektanggalpenjualan();

-- Trigger: tb_cekupdatetanggalpenjualan on toko_griyanaura.tr_penjualan

-- DROP TRIGGER tb_cekupdatetanggalpenjualan ON toko_griyanaura.tr_penjualan;

CREATE TRIGGER tb_cekupdatetanggalpenjualan
  BEFORE UPDATE
  ON toko_griyanaura.tr_penjualan
  FOR EACH ROW
  EXECUTE PROCEDURE toko_griyanaura.ftb_cekupdatetanggalpenjualan();

alter table toko_griyanaura.tr_penjualandetail add column id_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict;
alter table toko_griyanaura.tr_penjualandetail add column id_penjualandetailparent int references toko_griyanaura.tr_penjualandetail(id_penjualandetail) on update cascade on delete restrict;