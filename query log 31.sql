alter table toko_griyanaura.tr_penjualan add column nama_customer varchar(100);

CREATE OR REPLACE FUNCTION toko_griyanaura.ftb_datacustomer()
  RETURNS trigger AS
$BODY$
declare
	_nama_customer varchar;
begin
	if (NEW.nama_customer is null) then
		_nama_customer := (select nama from toko_griyanaura.ms_kontak where id_kontak = NEW.id_kontak limit 1);
		NEW.nama_customer = _nama_customer;
	end if;
	
	return NEW;
end;
$BODY$
  LANGUAGE plpgsql;


CREATE TRIGGER tb_datacustomer
  BEFORE INSERT OR UPDATE
  ON toko_griyanaura.tr_penjualan
  FOR EACH ROW
  EXECUTE PROCEDURE toko_griyanaura.ftb_datacustomer();
