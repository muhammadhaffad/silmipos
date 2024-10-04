-- Function: toko_griyanaura.ftb_cektanggalpembelianalokasipembayaran()

-- DROP FUNCTION toko_griyanaura.ftb_cektanggalpembelianalokasipembayaran();

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_cektanggalpembelianalokasipembayaran()
  RETURNS trigger AS
$BODY$
declare
	_tanggalvalid timestamp(0);
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
	select tanggal into _tanggalvalid from (
		select tanggal from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianpembayaran = NEW.id_pembelianpembayaran /*SOC*/
		union 
		select tanggal from toko_griyanaura.tr_pembelianrefunddetail prd join toko_griyanaura.tr_pembelianrefund pr using (id_pembelianrefund) where prd.id_pembelianpembayaran = NEW.id_pembelianpembayaran /*SOC*/
		union
		select tanggal from toko_griyanaura.tr_pembelianretur where id_pembelian = NEW.id_pembelianinvoice /*SOC*/
		) x
	order by tanggal desc limit 1;

	if (exists(select _tanggalvalid) and NEW.tanggal < _tanggalvalid) then
		raise exception 'tanggal harus lebih dari atau sama dengan %, karena terdapat transaksi sebelumnya', _tanggalvalid;
	end if;
	return NEW;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.ftb_cektanggalpembelianalokasipembayaran()
  OWNER TO postgres;
