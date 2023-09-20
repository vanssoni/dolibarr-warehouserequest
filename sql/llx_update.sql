

CREATE TABLE `llx_warehouserequest` (
  `rowid` int NOT NULL,
  `fk_commande` int NOT NULL,
  `fk_parent_line` int DEFAULT NULL,
  `fk_product` int DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` text,
  `vat_src_code` varchar(10) DEFAULT '',
  `tva_tx` double(7,4) DEFAULT NULL,
  `localtax1_tx` double(7,4) DEFAULT '0.0000',
  `localtax1_type` varchar(10)  DEFAULT NULL,
  `localtax2_tx` double(7,4) DEFAULT '0.0000',
  `localtax2_type` varchar(10)  DEFAULT NULL,
  `qty` double DEFAULT NULL,
  `remise_percent` double DEFAULT '0',
  `remise` double DEFAULT '0',
  `fk_remise_except` int DEFAULT NULL,
  `price` double DEFAULT NULL,
  `subprice` double(24,8) DEFAULT '0.00000000',
  `total_ht` double(24,8) DEFAULT '0.00000000',
  `total_tva` double(24,8) DEFAULT '0.00000000',
  `total_localtax1` double(24,8) DEFAULT '0.00000000',
  `total_localtax2` double(24,8) DEFAULT '0.00000000',
  `total_ttc` double(24,8) DEFAULT '0.00000000',
  `product_type` int DEFAULT '0',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `info_bits` int DEFAULT '0',
  `buy_price_ht` double(24,8) DEFAULT '0.00000000',
  `fk_product_fournisseur_price` int DEFAULT NULL,
  `special_code` int DEFAULT '0',
  `rang` int DEFAULT '0',
  `fk_unit` int DEFAULT NULL,
  `fk_user` int DEFAULT NULL,
  `import_key` varchar(14)  DEFAULT NULL,
  `ref_ext` varchar(255)  DEFAULT NULL,
  `fk_commandefourndet` int DEFAULT NULL,
  `fk_multicurrency` int DEFAULT NULL,
  `multicurrency_code` varchar(3)  DEFAULT NULL,
  `multicurrency_subprice` double(24,8) DEFAULT '0.00000000',
  `multicurrency_total_ht` double(24,8) DEFAULT '0.00000000',
  `multicurrency_total_tva` double(24,8) DEFAULT '0.00000000',
  `multicurrency_total_ttc` double(24,8) DEFAULT '0.00000000'
);
ALTER TABLE `llx_warehouserequest`
  ADD PRIMARY KEY (`rowid`);

ALTER TABLE `llx_warehouserequest`
  MODIFY `rowid` int NOT NULL AUTO_INCREMENT;
