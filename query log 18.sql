-- Function: toko_griyanaura.ftb_cekupdatetanggalpembelianpembayaranrefund()

-- DROP FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelianpembayaranrefund();

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelianpembayaranrefund()
  RETURNS trigger AS
$BODY$
declare
	_tanggalvalidmin timestamp(0);
	_tanggalvalidmax timestamp(0);
	_tanggalpembayaran timestamp(0);
begin
	_tanggalpembayaran := (select tanggal from toko_griyanaura.tr_pembelianpembayaran where id_pembelianpembayaran in (select id_pembelianpembayaran from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianrefund = NEW.id_pembelianrefund) order by tanggal desc limit 1);
	if (NEW.tanggal < _tanggalpembayaran) then 
		raise exception 'tanggal tidak boleh sebelum tanggal pembayaran pembelian';
	end if;
	select tanggal into _tanggalvalidmin from (
		select tanggal from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianpembayaran in (select id_pembelianpembayaran from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianrefund = NEW.id_pembelianrefund) and tanggal <= OLD.tanggal
		union 
		select tanggal from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianpembayaran in (select id_pembelianpembayaran from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianrefund = NEW.id_pembelianrefund) and tanggal <= OLD.tanggal
		union
		select tanggal from toko_griyanaura.tr_pembelianrefund where tanggal <= OLD.tanggal and id_pembelianrefund <> NEW.id_pembelianrefund
	) as x order by tanggal desc limit 1;

	select tanggal into _tanggalvalidmax from (
		select tanggal from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianpembayaran in (select id_pembelianpembayaran from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianrefund = NEW.id_pembelianrefund) and tanggal >= OLD.tanggal
		union 
		select tanggal from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianpembayaran in (select id_pembelianpembayaran from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianrefund = NEW.id_pembelianrefund) and tanggal >= OLD.tanggal
		union
		select tanggal from toko_griyanaura.tr_pembelianrefund where tanggal >= OLD.tanggal and id_pembelianrefund <> NEW.id_pembelianrefund
	) as x order by tanggal asc limit 1;

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
ALTER FUNCTION toko_griyanaura.ftb_cekupdatetanggalpembelianpembayaranrefund()
  OWNER TO postgres;
