insert into toko_griyanaura.lv_transaksigrup (id_transaksigrup, nama) values 
('penjualan', 'Penjualan'),
('penjualanalokasipembayaran', 'Alokasi Pembayaran Penjualan'),
('penjualanpembayaran', 'Penjualan Pembayaran'),
('penjualanrefund', 'Refund Penjualan'),
('penjualanretur', 'Retur Penjualan'),
('penjualanreturalokasikembaliandana', 'Alokasi Kembalian Dana Retur Penjualan');

insert into toko_griyanaura.lv_transaksijenis (id_transaksijenis, id_transaksigrup, nama) values 
('penjualan_invoice','penjualan', 'Invoice Penjualan'),
('penjualan_order','penjualan', 'Order Penjualan'),
('penjualanalokasipembayaran_dp','penjualanalokasipembayaran', 'Alokasi Pembayaran DP Penjualan'),
('penjualanpembayaran_dp','penjualanpembayaran', 'Penjualan Pembayaran DP'),
('penjualanpembayaran_refund','penjualanpembayaran', 'Refund Penjualan Pembayaran'),
('penjualanpembayaran_tunai','penjualanpembayaran', 'Penjualan Pembayaran Tunai'),
('penjualanretur','penjualanretur', 'Retur Penjualan'),
('penjualanreturalokasikembaliandana_dp','penjualanreturalokasikembaliandana', 'Alokasi Kembalian Dana Retur Penjualan');