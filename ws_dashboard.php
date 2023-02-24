#!/usr/bin/php7
<?php

if (PHP_MAJOR_VERSION >= 7) {
	#error_reporting(E_ALL ^ (E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_STRICT  | E_CORE_ERROR | E_PARSE | E_USER_NOTICE | E_RECOVERABLE_ERROR | E_WARNING | E_USER_WARNING | E_USER_ERROR | E_COMPILE_WARNING | E_CORE_WARNING | E_ERROR));
	#error_reporting(E_ALL ^ (E_DEPRECATED | E_USER_DEPRECATED | E_ERROR));
	error_reporting(E_ALL & ~E_NOTICE);
}

ini_set('memory_limit','512M');
ini_set('max_execution_time', '0');

$basedir	= dirname(realpath(__FILE__),3);

set_include_path($basedir."/WS-UMEE-DEV/");
include("global_function.php");
// include("global_variables.php");
include("PHP_CONNECTION/connection_neo.php");

include("FIXING/array_kodewilayah.php");

if(!$DBO->dbi) {
	echo "DB  not connect to ".$dbName."!";
	exit;
} else {
	$Ymd = date('Y-m-d');
	$Y = date('Y');
	// $Y = '2017';

	// $paramsDemografi = array(
	// 	"cod" => $Ymd,
	// 	"year" => $Y,
	// 	"query" => "SET umi.cod = '2023-02-23'; SELECT tahun, bulan, CASE WHEN kodepenyalur like '010%' THEN 'BAV' ELSE linkage END AS lkbb, provinsi, kabkota, linkage, jk, skema, groupsektor, sektor, jenisakad, CASE WHEN nilaiakad < 2500001 THEN '0 - 2.500.000' WHEN nilaiakad < 5000001 THEN '2.500.001 - 5.000.000' WHEN nilaiakad < 7500001 THEN '5.000.001 - 7.500.000' WHEN nilaiakad < 10000001 THEN '7.500.001 - 10.000.000' ELSE '> 10.000.000' END AS pembiayaan, CASE WHEN usia < 20 THEN '< 20' WHEN usia >= 20 AND usia < 30 THEN '20-29' WHEN usia >= 30 AND usia < 40 THEN '30-39' WHEN usia >= 40 AND usia < 50 THEN '40-49' ELSE '>=50' END AS usia, CASE WHEN (SELECT ((DATE_PART('year', tanggaljatuhtempo) - DATE_PART('year', tanggalakad)) * 12 + DATE_PART('month', tanggaljatuhtempo)- DATE_PART('month', tanggalakad))) < 6 THEN '< 6 bulan' WHEN (SELECT ((DATE_PART('year', tanggaljatuhtempo) - DATE_PART('year', tanggalakad)) * 12 + DATE_PART('month', tanggaljatuhtempo)- DATE_PART('month', tanggalakad))) <= 12 AND (SELECT ((DATE_PART('year', tanggaljatuhtempo) - DATE_PART('year', tanggalakad)) * 12 + DATE_PART('month', tanggaljatuhtempo)- DATE_PART('month', tanggalakad))) >= 6 THEN '6-12 bulan' WHEN (SELECT ((DATE_PART('year', tanggaljatuhtempo) - DATE_PART('year', tanggalakad)) * 12 + DATE_PART('month', tanggaljatuhtempo)- DATE_PART('month', tanggalakad))) > 12 THEN '> 12 bulan' END AS tenor, COUNT(noakad) AS debitur, SUM(nilaiakad) AS penyaluran FROM sikpumi.v_report where tahun = '2023' GROUP BY 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14 ORDER BY 1, 2, 3, 4, 5"
	// );

	// $resDemografi = fetchData('POST', 'http://192.168.10.21:5001/queryexe',$paramsDemografi);

	$yData = $argv['2'];

	$vdCondition = "";
	if($yData !== 'all'){
		$vdCondition = "AND tahun = '$yData'";
	} 

	$sqlWS = "
		SELECT *
	    FROM $tbl_ws
	    WHERE 1=1 
	";

	$resWS = $DBO->query($sqlWS);
// dipantek
	$ttlrows = 0;
	while($rowWS = $DBO->fetch_assoc($resWS)) {

		if($argv['1'] == 'demografi' && $rowWS['type'] == 'ws_demografi' && $rowWS['status'] == '0'){

			#update service sedang di proses
			$sqlUpd = "UPDATE $tbl_ws SET status = '1' WHERE type = 'ws_demografi' ";
			$execUpdate = $DBO->query($sqlUpd);

			$paramsDemografi = array(
				"cod" => $Ymd,
				"year" => $Y
			);

			$resDemografi = fetchData('POST', 'http://192.168.10.21:5001/demografi',$paramsDemografi);

			if($resDemografi !== 'NOK' || $resDemografi !== 'Kesalahan koneksi Web Services.') {
				$sqlDel = "TRUNCATE TABLE $tbl_demografi_2023 ";
				$execInsert = $DBO->query($sqlDel);

				$row = 0;
				$sqlField = "INSERT INTO $tbl_demografi_2023 (bulan,debitur,groupsektor,jenisakad,jk,kabkota,linkage,pembiayaan,penyalur,penyaluran,provinsi,sektor,skema,tahun,tenor,usia) VALUES";

				foreach ($resDemografi as $kD => $vD) {
					$row++;
					$coma = $row !== 1 ? "," : "";

					$sqlValues .= "$coma ('".$vD['bulan']."','".$vD['debitur']."','".$vD['groupsektor']."','".$vD['jenisakad']."','".$vD['jk']."','".$vD['kabkota']."','".$vD['linkage']."','".$vD['pembiayaan']."','".$vD['penyalur']."','".$vD['penyaluran']."','".$vD['provinsi']."','".$vD['sektor']."','".$vD['skema']."','".$vD['tahun']."','".$vD['tenor']."','".$vD['usia']."') ";

					if($row == 2000){
						$execInsert = $DBO->query($sqlField.$sqlValues.';');
						if($execInsert){
							$sqlValues = NULL;
							$row = 0;
						}
					}
				}

				if($sqlValues){
					$execInsert = $DBO->query($sqlField.$sqlValues.';');
					if($execInsert){
						$sqlValues = NULL;
						$row = 0;
					}
				}
			}

			$sqlField = NULL;
			$resDemografi = NULL;
			$kD = NULL;
			$vD = NULL;

			#UPDATE SERVICE UPDATE DEMOGRASI TELAH SELESAI
			$sqlUpd = "UPDATE $tbl_ws SET status = '0' WHERE type = 'ws_demografi' ";
			$execUpdate = $DBO->query($sqlUpd);

		} elseif ($argv['1'] == 'ws_dashboard' && $rowWS['type'] == 'ws_dashboard' && $rowWS['status'] == '0') {

			#update service sedang di proses
			$sqlUpd = "UPDATE $tbl_ws SET status = '1' WHERE type = 'ws_dashboard' ";
			$execUpdate = $DBO->query($sqlUpd);

			// UPDATE DATA TARGET
			$sqlTarget = "
				SELECT SUM(debitur) AS ttldebitur, SUM(penyaluran) AS ttlpenyaluran FROM $tbl_demografi WHERE tahun = '".date('Y')."'
			";
			$resTarget = $DBO->query($sqlTarget);
			if($resTarget) {
				$rowTarget = $DBO->fetch_assoc($resTarget);

				$sqlUpdate = "
					UPDATE umi_mst_target SET ttldata = '".$rowTarget['ttldebitur']."' WHERE type = 'debitur'
				";
				$execUpdate = $DBO->query($sqlUpdate);

			}

			// UPDATE DATA DEBITUR, PENYALURAN
			$sqlDeb = "
				SELECT SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) AS ttlpenyaluran
				FROM
				(
					SELECT SUM(debitur) AS ttldebitur, SUM(penyaluran) AS ttlpenyaluran FROM $tbl_demografi WHERE 1=1
					UNION 
					SELECT SUM(debitur) AS ttldebitur, SUM(penyaluran) AS ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1
				) vd
			";
			$resDeb1 = $DBO->query($sqlDeb);
			if($resDeb1) {
				$rowDeb = $DBO->fetch_assoc($resDeb1);
				$ttldebitur = $rowDeb['ttldebitur'];

				if($yData == 'all') {
					$sqlUpdate = "UPDATE $tbl_dashboard SET total_debitur = '".$rowDeb['ttldebitur']."', total_penyaluran = '".$rowDeb['ttlpenyaluran']."' ";
					$execUpdate = $DBO->query($sqlUpdate);
				}
			}

			#UPDATE DATA DEBITUR AKTIF, OSL PENYALURAN, PEMBIAYAAN, OSL PEMBIAYAAN
			$sqlDeb = "
				SELECT SUM(debituraktif) AS ttldebituraktif, SUM(oslpenyaluran) AS oslpenyaluran, SUM(totalpencairan) AS ttlpembiayaan, SUM(oslpembiayaan) AS oslpembiayaan FROM $tbl_outstanding
			";
			$resDeb2 = $DBO->query($sqlDeb);
			if($resDeb2) {
				$rowDeb = $DBO->fetch_assoc($resDeb2);

				$sqlUpdate = "UPDATE $tbl_dashboard SET total_debitur_aktif = '".$rowDeb['ttldebituraktif']."', total_penyaluran_osl = '".$rowDeb['oslpenyaluran']."', total_pembiayaan = '".$rowDeb['ttlpembiayaan']."', total_pengembalian = '".$rowDeb['oslpembiayaan']."', moddate = NOW() ";
				$execUpdate = $DBO->query($sqlUpdate);
			}

			#UPDATE DATA DASHBOARD MAP
			$sqlMap = "
				SELECT vd.provinsi, vd.latitude, vd.longitude, SUM(vd.ttldebitur) AS ttldebitur, SUM(vd.ttlpenyaluran) AS ttlpenyaluran
				FROM
				(
					SELECT dmg.provinsi, SUM(dmg.debitur) AS ttldebitur, SUM(dmg.penyaluran) AS ttlpenyaluran, crd.latitude, crd.longitude FROM $tbl_demografi dmg LEFT JOIN $tbl_coordinate crd ON (dmg.provinsi=crd.name) GROUP BY dmg.provinsi
					UNION 
					SELECT dmg.provinsi, SUM(dmg.debitur) AS ttldebitur, SUM(dmg.penyaluran) AS ttlpenyaluran, crd.latitude, crd.longitude FROM $tbl_demografi_2023 dmg LEFT JOIN $tbl_coordinate crd ON (dmg.provinsi=crd.name) GROUP BY dmg.provinsi
				) vd
				GROUP BY 1,2,3	
			";
			$resMap = $DBO->query($sqlMap);
			if($resMap) {
				while($rowMap = $DBO->fetch_assoc($resMap)) {
					$sqlUpdate = "
						INSERT INTO $tbl_dashboard_map (nama, debitur, penyaluran, latitude, longitude)
						VALUES ('".$rowMap['provinsi']."','".$rowMap['ttldebitur']."','".$rowMap['ttlpenyaluran']."','".$rowMap['latitude']."','".$rowMap['longitude']."')
						ON DUPLICATE KEY UPDATE debitur = '".$rowMap['ttldebitur']."', penyaluran = '".$rowMap['ttlpenyaluran']."', latitude = '".$rowMap['latitude']."', longitude = '".$rowMap['longitude']."';
					";
					$execUpdate = $DBO->query($sqlUpdate);
				}
			}

			#UPDATE SERVICE UPDATE DEMOGRASI TELAH SELESAI
			$sqlUpd = "UPDATE $tbl_ws SET status = '0' WHERE type = 'ws_dashboard' ";
			$execUpdate = $DBO->query($sqlUpd);

		} elseif ($argv['1'] == 'ws_dashboard_chart' && $rowWS['type'] == 'ws_dashboard_chart' && $rowWS['status'] == '0') {

			#update service sedang di proses
			$sqlUpd = "UPDATE $tbl_ws SET status = '1' WHERE type = 'ws_dashboard_chart' ";
			$execUpdate = $DBO->query($sqlUpd);

			#AMBIL TOTAL DEBITUR TAHUN BERJALAN
			$sqlDeb = "
				SELECT SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) AS ttlpenyaluran
				FROM
				(
					SELECT SUM(debitur) AS ttldebitur, SUM(penyaluran) AS ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition
					UNION 
					SELECT SUM(debitur) AS ttldebitur, SUM(penyaluran) AS ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition
				) vd
			";
			$resDeb1 = $DBO->query($sqlDeb);
			if($resDeb1) {
				$rowDeb = $DBO->fetch_assoc($resDeb1);
				$ttldebitur = $rowDeb['ttldebitur'];
			}

			#UPDATE DATA CHART GENDER
			$sqlChart1 = "
				SELECT vd.jk, SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) as ttlpenyaluran
				FROM (
					SELECT jk, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition GROUP BY jk
					UNION 
					SELECT jk, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition GROUP BY jk
				) vd
				GROUP BY 1
			";
			$resChart1 = $DBO->query($sqlChart1);
			if($resChart1) {
				while($rowChart1 = $DBO->fetch_assoc($resChart1)) {

					$persentase	= number_format((float)($rowChart1['ttldebitur']/$ttldebitur)*100, 0, '.', '');
					// $sqlInsert 	= "INSERT INTO $tbl_dashboard_chart (type,nama,ttldebitur,ttlpenyaluran,persentase,tahun) VALUES ('jk','".$rowChart1['jk']."','".$rowChart1['ttldebitur']."','".$rowChart1['ttlpenyaluran']."','".$persentase."','$yData')";
					$sqlUpdate 	= "UPDATE $tbl_dashboard_chart SET ttldebitur = '".$rowChart1['ttldebitur']."', ttlpenyaluran = '".$rowChart1['ttlpenyaluran']."', persentase = '$persentase' WHERE type = 'jk' AND nama = '".$rowChart1['jk']."' AND tahun = '$yData'";
					$execUpdate = $DBO->query($sqlUpdate);

				}
			}

			#UPDATE DATA CHART USIA
			$sqlChart2 = "
				SELECT vd.usia, SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) as ttlpenyaluran
				FROM (
					SELECT usia, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition GROUP BY usia
					UNION 
					SELECT usia, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition GROUP BY usia
				) vd
				GROUP BY 1
			";
			$resChart2 = $DBO->query($sqlChart2);
			if($resChart2) {
				while($rowChart2 = $DBO->fetch_assoc($resChart2)) {

					$persentase	= number_format((float)($rowChart2['ttldebitur']/$ttldebitur)*100, 0, '.', '');
					// $sqlUpdate 	= "INSERT INTO $tbl_dashboard_chart (type,nama,ttldebitur,ttlpenyaluran,persentase,tahun) VALUES ('usia','".$rowChart2['usia']."','".$rowChart2['ttldebitur']."','".$rowChart2['ttlpenyaluran']."','".$persentase."','$yData')";
					// $execUpdate = $DBO->query($sqlUpdate);

					$sqlUpdate 	= "UPDATE $tbl_dashboard_chart SET ttldebitur = '".$rowChart2['ttldebitur']."', ttlpenyaluran = '".$rowChart2['ttlpenyaluran']."', persentase = '$persentase' WHERE type = 'usia' AND nama = '".$rowChart2['usia']."' AND tahun = '$yData'";
					$execUpdate = $DBO->query($sqlUpdate);

				}
			}

			// UPDATE DATA CHART PEMBIAYAAN
			// $sqlChart3 = "
			// 	SELECT vd.pembiayaan, SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) as ttlpenyaluran
			// 	FROM (
			// 		SELECT pembiayaan, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition GROUP BY pembiayaan
			// 		UNION 
			// 		SELECT pembiayaan, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition GROUP BY pembiayaan
			// 	) vd
			// 	GROUP BY 1
			// ";
			// $resChart3 = $DBO->query($sqlChart3);
			// if($resChart3) {
			// 	while($rowChart3 = $DBO->fetch_assoc($resChart3)) {

			// 		$persentase	= number_format((float)($rowChart3['ttldebitur']/$ttldebitur)*100, 0, '.', '');
			// 		$sqlUpdate 	= "INSERT INTO $tbl_dashboard_chart (type,nama,ttldebitur,ttlpenyaluran,persentase,tahun) VALUES ('pembiayaan','".$rowChart3['pembiayaan']."','".$rowChart3['ttldebitur']."','".$rowChart3['ttlpenyaluran']."','".$persentase."','$yData') ON DUPLICATE KEY UPDATE ttldebitur = '".$resChart3['ttldebitur']."', ttlpenyaluran = '".$resChart3['ttlpenyaluran']."', persentase = '$persentase' ";
			// 		$execUpdate = $DBO->query($sqlUpdate);

			// 		// $sqlUpdate 	= "UPDATE $tbl_dashboard_chart SET ttldebitur = '".$resChart3['ttldebitur']."', ttlpenyaluran = '".$resChart3['ttlpenyaluran']."', persentase = '$persentase' WHERE type = 'pembiayaan' AND nama = '".$resChart3['pembiayaan']."' AND tahun = '$yData'";
			// 		// $execUpdate = $DBO->query($sqlUpdate);

			// 		// $sqlInsert = "INSERT INTO $tbl_dashboard_chart (nama, debitur, penyaluran, latitude, longitude)
			// 		// 	VALUES ('".$rowMap['provinsi']."','".$rowMap['ttldebitur']."','".$rowMap['ttlpenyaluran']."','".$rowMap['latitude']."','".$rowMap['longitude']."')
			// 		// 	ON DUPLICATE KEY UPDATE debitur = '".$rowMap['ttldebitur']."', penyaluran = '".$rowMap['ttlpenyaluran']."', latitude = '".$rowMap['latitude']."', longitude = '".$rowMap['longitude']."';
			// 		// 	";

			// 	}
			// }

			#UPDATE DATA CHART PEMBIAYAAN
			$sqlChart4 = "
				SELECT vd.tenor, SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) as ttlpenyaluran
				FROM (
					SELECT tenor, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition GROUP BY tenor
					UNION 
					SELECT tenor, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition GROUP BY tenor
				) vd
				GROUP BY 1
			";
			$resChart4 = $DBO->query($sqlChart4);
			if($resChart4) {
				while($rowChart4 = $DBO->fetch_assoc($resChart4)) {

					$persentase	= number_format((float)($rowChart4['ttldebitur']/$ttldebitur)*100, 0, '.', '');
					// $sqlUpdate 	= "INSERT INTO $tbl_dashboard_chart (type,nama,ttldebitur,ttlpenyaluran,persentase,tahun) VALUES ('tenor','".$rowChart4['tenor']."','".$rowChart4['ttldebitur']."','".$rowChart4['ttlpenyaluran']."','".$persentase."','$yData')";
					// $execUpdate = $DBO->query($sqlUpdate);

					$sqlUpdate 	= "UPDATE $tbl_dashboard_chart SET ttldebitur = '".$rowChart4['ttldebitur']."', ttlpenyaluran = '".$rowChart4['ttlpenyaluran']."', persentase = '$persentase' WHERE type = 'tenor' AND nama = '".$rowChart4['tenor']."' AND tahun = '$yData'";
					$execUpdate = $DBO->query($sqlUpdate);

				}
			}

			#UPDATE DATA CHART JENIS AKAD
			$sqlChart5 = "
				SELECT vd.jenisakad, SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) as ttlpenyaluran
				FROM (
					SELECT jenisakad, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition GROUP BY jenisakad
					UNION 
					SELECT jenisakad, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition GROUP BY jenisakad
				) vd
				GROUP BY 1
			";
			$resChart5 = $DBO->query($sqlChart5);
			if($resChart5) {
				while($rowChart5 = $DBO->fetch_assoc($resChart5)) {

					$persentase	= number_format((float)($rowChart5['ttldebitur']/$ttldebitur)*100, 0, '.', '');
					// $sqlUpdate 	= "INSERT INTO $tbl_dashboard_chart (type,nama,ttldebitur,ttlpenyaluran,persentase,tahun) VALUES ('jenisakad','".$rowChart5['jenisakad']."','".$rowChart5['ttldebitur']."','".$rowChart5['ttlpenyaluran']."','".$persentase."','$yData')";
					// $execUpdate = $DBO->query($sqlUpdate);

					$sqlUpdate 	= "UPDATE $tbl_dashboard_chart SET ttldebitur = '".$rowChart5['ttldebitur']."', ttlpenyaluran = '".$rowChart5['ttlpenyaluran']."', persentase = '$persentase' WHERE type = 'jenisakad' AND nama = '".$rowChart5['jenisakad']."' AND tahun = '$yData'";
					$execUpdate = $DBO->query($sqlUpdate);

				}
			}

			#UPDATE DATA CHART SKEMA
			$sqlChart6 = "
				SELECT vd.skema, SUM(vd.ttldebitur) as ttldebitur, SUM(vd.ttlpenyaluran) as ttlpenyaluran
				FROM (
					SELECT skema, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi WHERE 1=1 $vdCondition GROUP BY skema
					UNION 
					SELECT skema, SUM(debitur) as ttldebitur, SUM(penyaluran) as ttlpenyaluran FROM $tbl_demografi_2023 WHERE 1=1 $vdCondition GROUP BY skema
				) vd
				GROUP BY 1
			";
			$resChart6 = $DBO->query($sqlChart6);
			if($resChart6) {
				while($rowChart6 = $DBO->fetch_assoc($resChart6)) {

					$persentase	= number_format((float)($rowChart6['ttldebitur']/$ttldebitur)*100, 0, '.', '');
					// $sqlUpdate 	= "INSERT INTO $tbl_dashboard_chart (type,nama,ttldebitur,ttlpenyaluran,persentase,tahun) VALUES ('skema','".$rowChart6['skema']."','".$rowChart6['ttldebitur']."','".$rowChart6['ttlpenyaluran']."','".$persentase."','$yData')";
					// $execUpdate = $DBO->query($sqlUpdate);

					$sqlUpdate 	= "UPDATE $tbl_dashboard_chart SET ttldebitur = '".$rowChart6['ttldebitur']."', ttlpenyaluran = '".$rowChart6['ttlpenyaluran']."', persentase = '$persentase' WHERE type = 'skema' AND nama = '".$rowChart6['skema']."' AND tahun = '$yData'";
					$execUpdate = $DBO->query($sqlUpdate);

				}
			}

			#UPDATE SERVICE UPDATE DEMOGRASI TELAH SELESAI
			$sqlUpd = "UPDATE $tbl_ws SET status = '0' WHERE type = 'ws_dashboard_chart' ";
			$execUpdate = $DBO->query($sqlUpd);

		} elseif ($argv['1'] == 'ws_penyaluran_harian' && $rowWS['type'] == 'ws_penyaluran_harian' && $rowWS['status'] == '0') {

			#update service sedang di proses
			$sqlUpd = "UPDATE $tbl_ws SET status = '1' WHERE type = 'ws_penyaluran_harian' ";
			$execUpdate = $DBO->query($sqlUpd);


			#UPDATE SERVICE UPDATE DEMOGRASI TELAH SELESAI
			$sqlUpd = "UPDATE $tbl_ws SET status = '0' WHERE type = 'ws_penyaluran_harian' ";
			$execUpdate = $DBO->query($sqlUpd);

		}	

		// #RESET ALL SERVICE UNTUK BISA DIPROSES SELANJUTNYA
		// $sqlUpd = "UPDATE $tbl_ws SET status = '0' ";
		// $execUpdate = $DBO->query($sqlUpd);		
	}
	
}

exit;
?>