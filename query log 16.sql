-- Function: toko_griyanaura.ftb_cekupdatetanggalpembelianalokasipembayaran()

-- DROP FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelianalokasipembayaran();

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelianalokasipembayaran()
  RETURNS trigger AS
$BODY$
declare
	_tanggalvalidmin timestamp(0);
	_tanggalvalidmax timestamp(0);
	_tanggalpembayaran timestamp(0);
	_tanggalinvoice timestamp(0);
begin
	_tanggalpembayaran := (select tanggal from toko_griyanaura.tr_pembelianpembayaran where id_pembelianpembayaran = NEW.id_pembelianpembayaran);
	_tanggalinvoice := (select tanggal from toko_griyanaura.tr_pembelian where id_pembelian = NEW.id_pembelianinvoice);
	if (NEW.tanggal < _tanggalpembayaran) then 
		raise exception 'tanggal tidak boleh sebelum tanggal pembayaran pembelian';
	end if;
	if (NEW.tanggal < _tanggalinvoice) then
		raise exception 'tanggal tidak boleh sebelum tanggal invoice pembelian';
	end if;
	select tanggal into _tanggalvalidmin from (
		select tanggal from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianpembayaran = NEW.id_pembelianpembayaran and tanggal <= OLD.tanggal /*SOC*/
		union 
		select tanggal from toko_griyanaura.tr_pembelianrefunddetail prd join toko_griyanaura.tr_pembelianrefund pr using (id_pembelianrefund) where prd.id_pembelianpembayaran = NEW.id_pembelianpembayaran and pr.tanggal <= OLD.tanggal /*SOC*/
		union
		select tanggal from toko_griyanaura.tr_pembelianretur where id_pembelian = NEW.id_pembelianinvoice and tanggal <= OLD.tanggal /*SOC*/
		union 
		select tanggal from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianpembayaran = NEW.id_pembelianpembayaran and tanggal <= OLD.tanggal and id_pembelianalokasipembayaran <> NEW.id_pembelianalokasipembayaran
	) x
	order by tanggal desc limit 1;

	select tanggal into _tanggalvalidmax from (
		select tanggal from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianpembayaran = NEW.id_pembelianpembayaran and tanggal >= OLD.tanggal /*SOC*/
		union 
		select tanggal from toko_griyanaura.tr_pembelianrefunddetail prd join toko_griyanaura.tr_pembelianrefund pr using (id_pembelianrefund) where prd.id_pembelianpembayaran = NEW.id_pembelianpembayaran and pr.tanggal >= OLD.tanggal /*SOC*/
		union
		select tanggal from toko_griyanaura.tr_pembelianretur where id_pembelian = NEW.id_pembelianinvoice and tanggal >= OLD.tanggal /*SOC*/
		union 
		select tanggal from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianpembayaran = NEW.id_pembelianpembayaran and tanggal >= OLD.tanggal and id_pembelianalokasipembayaran <> NEW.id_pembelianalokasipembayaran
	) x
	order by tanggal asc limit 1;

	if (exists(select _tanggalvalidmin) and NEW.tanggal < _tanggalvalidmin) then
		raise exception 'tanggal harus lebih dari atau sama dengan %, karena terdapat transaksi sebelumnya', _tanggalvalidmin;
	end if;
	if (exists(select _tanggalvalidmax) and NEW.tanggal > _tanggalvalidmax) then
		raise exception 'tanggal harus kurang dari atau sama dengan %, karena terdapat transaksi sebelumnya', _tanggalvalidmax;
	end if;
	return NEW;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelianalokasipembayaran()
  OWNER TO postgres;
