<!-- Checking the selected options here -->

/*  Kranium Zoho Books Application Landing Page */
<?php 
    session_start();
    // Check if the user is authenticated
    if (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) {
        // Access the username from the session
        $username = $_SESSION['username'];
    } else {
        // Redirect to login page if not authenticated
        header("Location: ./auth.php");
        exit();
    }
    require_once "generate_access_key.php";
    require_once "config.php";
    if(isset($_POST["location"])){
        $date = $_POST["fromDate"];
        $toDate = $_POST["toDate"];
        $selectedLocation = $_POST["location"];
        $date1=date_create($date);
        $date2=date_create($toDate);
        $diff=date_diff($date1,$date2);
        $check =  $diff->format("%a");
        if($check <= "15"){
            if($selectedLocation == "stores"){
                require_once "test_bill.php";
                $getData = "SELECT gr.grnbatch, gr.grnno, gr.supplierid, gr.itemcode, gr.invoicedate, (
                    ROUND( gv.qtyrecd * gv.ucp, 2 )
                    ) AS total, s.suppname, TRIM( s.oldid ) AS zohoid, gv.qtyrecd AS qty, gv.ucp AS ItemTotal, DATE( gr.deliverydate ) AS DATE, TRIM( gv.itemdescription ) AS itemdescription, gv.pono, invoiceref AS invoice, gv.tax, TRIM( s.supGST_NO ) AS supGST_NO, gv.rate AS Rate,
                    CASE
                    WHEN gv.disval = 1
                    THEN (
                    gv.rate - ( (
                    gv.rate * gv.discount
                    ) / 100 )
                    )
                    ELSE (
                    (
                    gv.rate - gv.discount
                    )
                    )
                    END AS Actualrate,
                    CASE
                    WHEN gv.IGST > 0
                    THEN (
                    tar.zoho_igst_id
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 0
                    THEN (
                    tar.zoho_tax_id
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 1
                    THEN (
                    tar.zoho_igst_id
                    )
                    ELSE (
                    tar.zoho_tax_id
                    )
                    END AS zoho_tax_id,
                    CASE
                    WHEN gv.IGST > 0
                    THEN (
                    tar.zoho_igst_name
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 0
                    THEN (
                    tar.zoho_tax_name
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 1
                    THEN (
                    tar.zoho_igst_name
                    )
                    ELSE (
                    tar.zoho_tax_name
                    )
                    END AS zoho_tax_name, gv.discount, tar.Percentage, pt.producttype_name, pt.zoho_chartof_account_id, pt.producttype_code, sm.product_type
                    FROM grns gr, grnvalues gv, suppliers s, invoicedetails id, taxauthrates tar, stockmaster sm, producttype pt
                    WHERE gr.grnbatch = gv.grnbatch AND gr.grnno = gv.grnno AND gr.podetailitem = gv.podetailitem AND gr.itemcode = gv.poItems AND s.supplierid = gr.supplierid AND id.grnbatch = gv.grnbatch AND id.grnno = gv.grnno AND gv.tax = tar.Percentage AND sm.stockid = gv.poItems AND pt.producttype_code = sm.product_type AND gv.completed
                    IN ( 1, 2 )";
                // query from date 2023-04-25
                $result = $sql32s->query($getData);
                $dataArray = array();
                $resultArray = array();
                // Fetch all rows into an array
                while ($row = $result->fetch_assoc()) {
                    $dataArray[] = $row;
                }
                $output = '';
                // Group rows by vendor ID and grnbatch
                $groupedData = array();
                foreach ($dataArray as $row) {
                    if($row['zohoid'] != '0')
                    {
                        $vendorId = $row['zohoid']; 
                        $grnbatch = $row['grnbatch'];
                        $groupedData[$vendorId][$grnbatch][] = $row;
                    }
                }
                foreach ($groupedData as $vendorId => $vendorGrnData) {
                    foreach ($vendorGrnData as $grnbatch => $grnRows) {
                        // date, due date, gst_treatment, payment_terms_lable, payment_terms need to change dynamically
                        if ($vendorId)
                        {
                            $output .= '{
                                "vendor_id": "'.$vendorId.'",
                                "bill_number": "'.$grnRows[0]['invoice'].'",
                                "date": "'.$grnRows[0]['invoicedate'].'",
                                "due_date": "'.$grnRows[0]['invoicedate'].'",
                                "payment_terms": 0, 
                                "payment_terms_label": "Due on Receipt",
                                "gst_treatment": "business_gst",
                                "gst_no": "'.$grnRows[0]['supGST_NO'].'",
                                "reference_number": "'.$grnRows[0]['pono'].'",
                                "line_items": [';
                            foreach ($grnRows as $innerRow) {
                                //acount id need to changed dynamically 
                                $output .= '{
                                    "account_id": "'.$innerRow['ledgername'].'",
                                    "description": "'.str_replace(["\r\n", '"'],"", trim($innerRow['itemdescription'],"'\'")).'",
                                    "rate": "'.$innerRow['Actualrate'].'",
                                    "quantity": "'.$innerRow['qty'].'",
                                    "tax_id": "'.$innerRow['zoho_tax_id'].'",
                                    "tax_percentage": "'.$innerRow['tax'].'"
                                },';
                            }
                            $output = rtrim($output, ','); // Remove trailing comma from line_items
                            $output .= '],
                            "taxes": [';
                            foreach ($grnRows as $linetwo) {
                                $output .= '{
                                    "tax_id": "'.$linetwo['zoho_tax_id'].'",
                                    "tax_name": "'.$linetwo['zoho_tax_name'].'"
                                },';
                            }
                            $output = rtrim($output, ','); // Remove trailing comma from taxes
                            $output .= '],
                            "notes": "Thanks for your business.",
                            "terms": "Terms and conditions apply."
                            },';
                            $output = rtrim($output, ',');
                            // echo $output;
                            $query = "INSERT INTO store_error_log (`username`, `json`) VALUES ('".$vendorId."' , '".$output."')";
                            $sql32p->query($query);
                            if ($sql32p == null)
                            {
                              return "Not Updated";
                            }
                        }
                        $output='';
                    }
                }
                $bills = "SELECT * FROM `store_error_log` where is_ported = '0'";
                $find_bills = $sql32p->query($bills);
                while($row = $find_bills->fetch_assoc())
                {
                    // $createbill = CreateNewBill($orgId , $access_token, $row['json']);
                    if($createbill)
                    {
                        $update = "UPDATE `pharmacy_error_log` SET `is_ported` = '1' , `error_msg` = '".$createbill."' WHERE `json` = '".$row['json']."'";
                        $sql32p->query($update);
                    }
                    array_push($resultArray,$createbill);
                }
                // Remove trailing comma from last iteration
                $dummy = array();
                foreach($resultArray as $data)
                {
                    array_push($dummy , $data);
                }
                // print_r($resultArray);
                // echo $output;
            }
            elseif($selectedLocation == "pharmacy"){
                require_once "test_bill.php";
                $getData = "
                SELECT gr.grnbatch, gr.grnno, gr.supplierid, gr.itemcode, gr.invoicedate, (
                    ROUND( gv.qtyrecd * gv.ucp, 2 )
                    ) AS total, s.suppname, TRIM( s.zohoid ) AS zohoid, gv.qtyrecd AS qty, gv.ucp AS ItemTotal, DATE( gr.deliverydate ) AS DATE, TRIM( sm.longdescription ) AS itemdescription, gv.pono, invoiceref AS invoice, gv.tax, TRIM( s.supGST_NO ) AS supGST_NO, gv.rate AS Rate,
                    CASE
                    WHEN gv.disval = 1
                    THEN (
                    gv.rate - ( (
                    gv.rate * gv.discount
                    ) / 100 )
                    )
                    ELSE (
                    (
                    gv.rate - gv.discount
                    )
                    )
                    END AS Actualrate,
                    CASE
                    WHEN gv.IGST > 0
                    THEN (
                    tar.zoho_igst_id
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 0
                    THEN (
                    tar.zoho_tax_id
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 1
                    THEN (
                    tar.zoho_igst_id
                    )
                    ELSE (
                    tar.zoho_tax_id
                    )
                    END AS zoho_tax_id,
                    CASE
                    WHEN gv.IGST > 0
                    THEN (
                    tar.zoho_igst_name
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 0
                    THEN (
                    tar.zoho_tax_name
                    )
                    WHEN gv.GST = 0 AND s.suppstate = 1
                    THEN (
                    tar.zoho_igst_name
                    )
                    ELSE (
                    tar.zoho_tax_name
                    )
                    END AS zoho_tax_name, gv.discount, tar.Percentage, pt.producttype_name, pt.zoho_chartof_account_id, pt.producttype_code, sm.product_type
                    FROM grns gr, grnvalues gv, suppliers s, invoicedetails id, taxauthrates tar, stockmaster sm, producttype pt
                    WHERE id.invoiced_date  >= '".$date."' AND id.invoiced_date <= '".$toDate."' and gr.grnbatch = gv.grnbatch AND gr.grnno = gv.grnno AND gr.podetailitem = gv.podetailitem AND gr.itemcode = gv.poItems AND s.supplierid = gr.supplierid AND id.grnbatch = gv.grnbatch AND id.grnno = gv.grnno AND gv.tax = tar.Percentage AND sm.stockid = gv.poItems AND pt.producttype_code = sm.product_type   AND gv.completed
                    IN ( 1, 2 )";
                $result = $sql32p->query($getData);
                $dataArray = array();
                $resultArray = array();
                $truncate = "DELETE FROM `pharmacy_error_log` WHERE `is_ported` = '1'";
                $run_truncate = $sql32p->query($truncate);
                // Fetch all rows into an array
                while ($row = $result->fetch_assoc()) {
                    $dataArray[] = $row;
                }
                $output = '';
                // Group rows by vendor ID and grnbatch
                $groupedData = array();
                foreach ($dataArray as $row) {
                    if($row['zohoid'] != '0')
                    {
                        $vendorId = $row['zohoid']; 
                        $grnbatch = $row['grnbatch'];
                        $groupedData[$vendorId][$grnbatch][] = $row;
                    }
                }
                foreach ($groupedData as $vendorId => $vendorGrnData) 
                {
                    foreach ($vendorGrnData as $grnbatch => $grnRows) 
                    {
                        if ($vendorId)
                        {
                            $output .= '{
                            "vendor_id": "'.$vendorId.'",
                            "bill_number": "'.$grnRows[0]['invoice'].'",
                            "date": "'.$grnRows[0]['invoicedate'].'",
                            "due_date": "'.$grnRows[0]['invoicedate'].'",
                            "payment_terms": 0, 
                            "payment_terms_label": "Due on Receipt",
                            "gst_treatment": "business_gst",
                            "gst_no": "'.$grnRows[0]['supGST_NO'].'",
                            "reference_number": "'.$grnRows[0]['pono'].'",
                            "source_of_supply": "'.$grnRows[0]['gst_filename'].'",
                            "destination_of_supply": "TS",
                            "line_items": [';
                            foreach ($grnRows as $innerRow) {
                                //acount id need to changed dynamically 
                                $output .= '{
                                    "account_id": "'.$innerRow['zoho_chartof_account_id'].'", 
                                    "description": "'.str_replace(["\r\n", '"', "'"],"", trim($innerRow['itemdescription'],"'\'")).'",
                                    "rate": "'.$innerRow['Actualrate'].'",
                                    "quantity": "'.$innerRow['qty'].'",
                                    "tax_id": "'.$innerRow['zoho_tax_id'].'",
                                    "tax_percentage": "'.$innerRow['tax'].'"
                                },';
                            }
                            $output = rtrim($output, ','); // Remove trailing comma from line_items
                            $output .= '],
                            "taxes": [';
                            foreach ($grnRows as $linetwo) {
                                $output .= '{
                                    "tax_id": "'.$linetwo['zoho_tax_id'].'",
                                    "tax_name": "'.$linetwo['zoho_tax_name'].'"
                                },';
                            }
                            $output = rtrim($output, ','); // Remove trailing comma from taxes
                            $output .= '],
                            "notes": "Thanks for your business.",
                            "terms": "Terms and conditions apply."
                            },';
                            $output = rtrim($output, ',');
                            $query = "INSERT INTO pharmacy_error_log (`username`,`json`, `bill_no`) VALUES ('".$vendorId."' , '".$output."', '".$grnRows[0]['invoice']."')";
                            $sql32p->query($query);
                            if ($sql32p == null)
                            {
                                return "Not Updated";
                            }
                        }
                        $output='';
                    }
                }
                $bills = "SELECT * FROM `pharmacy_error_log` where `is_ported` = '0'";
                $find_bills = $sql32p->query($bills);
                while($row = $find_bills->fetch_assoc())
                {
                    $createbill = CreateNewBill($orgId , $access_token, $row['json']);
                    if($createbill)
                    {
                        $update = "UPDATE `pharmacy_error_log` SET `is_ported` = '1' , `error_msg` = '".$createbill."' WHERE `json` = '".$row['json']."'";
                        $sql32p->query($update);
                    }
                    array_push($resultArray,$createbill);
                }
                $dummy = array();
                foreach($resultArray as $data)
                {
                    array_push($dummy , $data);
                }
                $logdata = "SELECT * FROM `pharmacy_error_log`";
                $log_reuslt = $sql32p->query($logdata);
                if (mysqli_num_rows($log_reuslt) >= 1 && $date != '' ){
                    echo '<a class="exists" href="http://172.19.0.30/zohoportal/log.php?fromdate='.$date.'&todate='.$toDate.'"> Explore the ported Log </a>';
                    echo "<style>
                    a
                    {
                        text-decoration : none;
                        color : black;
                    }
                    .exists{
                        position: fixed;
                        border : none;
                        top: 0;
                        right: 0;
                        margin-top: 18rem;
                        margin-right: 8rem;
                        border-left: 1px solid red;
                        padding: 0.5rem 2rem;
                        background: #f6f6f6;
                        animation-name : rightTocenter;
                        animation-duration : 2s;
                    }
                    .exists::before{
                        width: 0%;
                        height: 2%;
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        background-color: red;
                        z-index: 1;
                        content: '';
                        transition : all 0.3s ease-in;
                        animation-name : bottomWidth;
                        animation-duration : 6s;
                    }
                    .exists:hover{
                        color : red;
                    }
                    @keyframes bottomWidth {

                        0%{
                            width : 0%;
                        }
                        100%{
                            width : 100%;
                        }
                    }
                    </style>";
                }
            }
            elseif($selectedLocation == "his")
            {
                $getData = "SELECT billdate AS BILLDATE, ledgername AS `LEDGER NAME` , ROUND( debit, 0 ) AS `DEBIT` , ROUND( credit, 0 ) AS `CREDIT` , zohoid
                FROM (
                SELECT billdate, IFNULL( SUM( debit ) , 0 ) AS debit, IFNULL( SUM( credit ) , 0 ) credit, ledgername, zp.zoho_id AS zohoid
                FROM (
                SELECT cbf.final_id AS finalbillno, cbf.final_encounter_nr AS enc, cbf.final_bill_no AS billno, cbf.final_date AS billdate, cbp.payment_receipt_no AS receiptno,
                CASE WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_cash_amount > 0 AND cbp.status = 'paid'
                THEN cbp.payment_cash_amount
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_cheque_amount > 0 AND cbp.status = 'paid'
                THEN cbp.payment_cheque_amount
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_creditcard_amount > 0 AND cbp.status = 'paid'
                THEN cbp.payment_creditcard_amount
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_dd_amt > 0 AND cbp.status = 'paid'
                THEN cbp.payment_dd_amt
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_ecs_amt > 0 AND cbp.status = 'paid'
                THEN cbp.payment_ecs_amt
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_cash_amount > 0 AND cbp.status = 'paid'
                THEN - 1 * cbp.payment_cash_amount
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_cheque_amount > 0 AND cbp.status = 'paid'
                THEN - 1 * cbp.payment_cheque_amount
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_creditcard_amount > 0 AND cbp.status = 'paid'
                THEN - 1 * cbp.payment_creditcard_amount
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_ecs_amt > 0 AND cbp.status = 'paid'
                THEN - 1 * cbp.payment_ecs_amt
                WHEN cbp.final_settlement = 3 AND cbp.payment_amount_total > 0
                THEN cbp.payment_amount_total
                WHEN cbp.final_settlement = 17 AND cbp.payment_amount_total > 0 AND cbp.status = 'paid'
                THEN cbp.payment_amount_total
                ELSE 0
                END AS debit, 0 AS credit,
                CASE WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_cash_amount > 0
                THEN 'CASH OP'
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_cheque_amount > 0
                THEN 'CHEQUE OP'
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_creditcard_amount > 0
                THEN 'CARD OP'
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_dd_amt > 0
                THEN 'BANKTRANSFER OP'
                WHEN cbp.final_settlement
                IN ( 0, 1 ) AND cbp.payment_ecs_amt > 0
                THEN 'BANKTRANSFER OP'
                WHEN cbp.final_settlement = 17 AND cbp.payment_amount_total > 0
                THEN 'DEPOSIT TRANSFER OP'
                WHEN cbp.final_settlement = 3 AND cbp.payment_amount_total > 0
                THEN 'CREDITVOUCHER OP'
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_cash_amount > 0
                THEN 'CASH OP'
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_cheque_amount > 0
                THEN 'CHEQUE OP'
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_creditcard_amount > 0
                THEN 'CARD OP'
                WHEN cbp.final_settlement = 2 AND ce.emergency = 1 AND cbp.payment_ecs_amt > 0
                THEN 'BANKTRANSFER OP'
                ELSE 'CASH OP'
                END AS ledgername
                FROM care_billing_final cbf
                LEFT JOIN care_billing_payment cbp ON cbf.final_encounter_nr = cbp.payment_encounter_nr AND cbf.final_bill_no = cbp.bill_no AND cbf.location_id = cbp.location_id
                LEFT JOIN care_encounter ce ON cbf.final_encounter_nr = ce.encounter_nr AND cbf.location_id = ce.location_id
                WHERE ce.encounter_class_nr = 2 AND cbp.payment_amount_total > 0 AND (
                cbf.final_date >= '$date' AND cbf.final_date <= '$toDate'
                ) AND (
                cbf.location_id = 90
                )
                UNION ALL
                SELECT cbf.final_id AS finalbillno, cbf.final_encounter_nr AS enc, cbf.final_bill_no AS billno, cbp.payment_date AS billdate, cbp.payment_receipt_no AS receiptno, 0 AS debit,
                CASE WHEN cbp.final_settlement = 2 AND cbp.payment_cash_amount > 0 AND cbp.status = 'paid'
                THEN cbp.payment_cash_amount
                WHEN cbp.final_settlement = 2 AND cbp.payment_cheque_amount > 0 AND cbp.status = 'paid'
                THEN cbp.payment_cheque_amount
                WHEN cbp.final_settlement = 2 AND cbp.payment_creditcard_amount > 0 AND cbp.status = 'paid'
                THEN cbp.payment_creditcard_amount
                WHEN cbp.final_settlement = 2 AND cbp.payment_ecs_amt > 0 AND cbp.status = 'paid'
                THEN cbp.payment_ecs_amt
                ELSE 0
                END AS credit,
                CASE WHEN cbp.final_settlement = 2 AND cbp.payment_cash_amount > 0
                THEN 'CASH REFUND OP'
                WHEN cbp.final_settlement = 2 AND cbp.payment_cheque_amount > 0
                THEN 'CHEQUE REFUND OP'
                WHEN cbp.final_settlement = 2 AND cbp.payment_creditcard_amount > 0
                THEN 'CARD REFUND OP'
                WHEN cbp.final_settlement = 2 AND cbp.payment_ecs_amt > 0
                THEN 'BANKTRANSFER REFUND OP'
                END AS ledgername
                FROM care_billing_final cbf
                LEFT JOIN care_billing_payment cbp ON cbf.final_encounter_nr = cbp.payment_encounter_nr AND cbf.final_bill_no = cbp.bill_no AND cbf.location_id = cbp.location_id
                LEFT JOIN care_encounter ce ON cbf.final_encounter_nr = ce.encounter_nr AND cbf.location_id = ce.location_id
                WHERE ce.encounter_class_nr = 2 AND ce.emergency = 0 AND cbp.final_settlement
                IN ( 2 ) AND cbp.payment_amount_total > 0 AND (
                cbp.payment_date >= '$date' AND cbp.payment_date <= '$toDate'
                ) AND (
                cbp.location_id = 90
                )
                UNION ALL
                SELECT cbf.final_id AS finalbillno, cbf.final_encounter_nr AS enc, cbf.final_bill_no AS billno, cbp.payment_date AS billdate, cbp.payment_receipt_no AS receiptno, 0 AS debit,
                CASE
                WHEN cbp.final_settlement = 3 AND cbp.payment_amount_total < 0
                THEN ABS( cbp.payment_amount_total )
                ELSE 0
                END AS credit,
                CASE
                WHEN cbp.final_settlement = 3 AND cbp.payment_amount_total < 0
                THEN 'CREDITVOUCHER REFUND OP'
                END AS ledgername
                FROM care_billing_final cbf
                LEFT JOIN care_billing_payment cbp ON cbf.final_encounter_nr = cbp.payment_encounter_nr AND cbf.final_bill_no = cbp.bill_no AND cbf.location_id = cbp.location_id
                LEFT JOIN care_encounter ce ON cbf.final_encounter_nr = ce.encounter_nr AND cbf.location_id = ce.location_id
                WHERE ce.encounter_class_nr = 2 AND cbp.final_settlement = 3 AND cbp.payment_amount_total < 0 AND (
                cbp.payment_date >= '$date' AND cbp.payment_date <= '$toDate'
                ) AND (
                cbp.location_id = 90
                )
                UNION ALL
                SELECT cbf.final_id AS finalbillno, cbf.final_encounter_nr AS enc, cbf.final_bill_no AS billno, cbf.final_date AS billdate, 0 AS receiptno, IFNULL( cbf.final_discount, 0 ) AS debit, 0 AS credit, 'DISCOUNT OP' AS ledgername
                FROM care_billing_final AS cbf, care_encounter ce
                WHERE ce.encounter_nr = cbf.final_encounter_nr AND ce.location_id = cbf.location_id AND ce.encounter_class_nr = 2 AND cbf.final_discount > 0 AND (
                cbf.final_date >= '$date' AND cbf.final_date <= '$toDate'
                ) AND (
                cbf.location_id = 90
                )
                ) AS t
                LEFT JOIN zoho_paymentmode zp ON t.ledgername = zp.description
                GROUP BY billdate, ledgername
                UNION ALL
                SELECT billdate, IFNULL( SUM( debit ) , 0 ) AS debit, IFNULL( SUM( credit ) , 0 ) AS credit, ledgername, zohoid
                FROM (
                SELECT billdate, 0 AS debit, amt AS credit, CONCAT( UPPER( cat ) , ' OP' ) AS ledgername, zoho_coa_op AS zohoid
                FROM (
                SELECT cbf.final_id AS finalbillno, cbf.final_encounter_nr AS enc, cbf.final_bill_no AS billno, cbf.final_date AS billdate, cbbi.bill_item_amount AS amt, IFNULL(
                IF (
                cchs.name IS NULL ,
                IF (
                ctp.reportmaingroupname = 'Radiology', ctp.reportsubgroupname, ctp.reportmaingroupname
                ), cchs.name ) , 'OTHERS'
                ) AS cat, IFNULL(
                IF (
                cchs.zoho_chartof_acc_op IS NULL , ctp.zoho_chartof_acc_op, cchs.zoho_chartof_acc_op
                ), '1434248000000000619' ) AS zoho_coa_op, cbbi.bill_item_id AS id
                FROM care_billing_final AS cbf, care_billing_bill_item AS cbbi
                LEFT JOIN care_hospital_services AS chs ON cbbi.bill_item_code = chs.code AND cbbi.location_id = chs.location_id
                LEFT JOIN care_category_hospital_services AS cchs ON chs.category = cchs.category AND chs.location_id = cchs.location_id
                LEFT JOIN care_test_param AS ctp ON cbbi.bill_item_code = ctp.id AND cbbi.location_id = ctp.location_id, care_encounter AS ce
                WHERE (
                cbf.final_date >= '$date' AND cbf.final_date <= '$toDate'
                ) AND (
                cbf.location_id = 90
                ) AND cbf.final_encounter_nr = cbbi.bill_item_encounter_nr AND cbbi.bill_item_bill_no = cbf.final_bill_no AND cbf.location_id = cbbi.location_id AND cbbi.status = '' AND ce.encounter_nr = cbbi.bill_item_encounter_nr AND ce.location_id = cbbi.location_id AND ce.encounter_class_nr = 2
                ) AS bb
                UNION ALL
                SELECT billdate, amt AS debit, 0 AS credit, CONCAT( UPPER( cat ) , ' OP' ) AS ledgername, zoho_coa_op AS zohoid
                FROM (
                SELECT cbf.final_id AS finalbillno, cbf.final_encounter_nr AS enc, cbf.final_bill_no AS billno, cbbi.bill_item_cancel_date AS billdate, cbbi.bill_item_after_dis_amt AS amt, IFNULL(
                IF (
                cchs.name IS NULL ,
                IF (
                ctp.reportmaingroupname = 'Radiology', ctp.reportsubgroupname, ctp.reportmaingroupname
                ), cchs.name ) , 'OTHERS'
                ) AS cat, IFNULL(
                IF (
                cchs.zoho_chartof_acc_op IS NULL , ctp.zoho_chartof_acc_op, cchs.zoho_chartof_acc_op
                ), '1434248000000000619' ) AS zoho_coa_op, cbbi.bill_item_cancel_nr AS id
                FROM care_billing_final AS cbf, care_cancel_billing_bill_item AS cbbi
                LEFT JOIN care_hospital_services AS chs ON cbbi.bill_item_code = chs.code AND cbbi.location_id = chs.location_id
                LEFT JOIN care_category_hospital_services AS cchs ON chs.category = cchs.category AND chs.location_id = cchs.location_id
                LEFT JOIN care_test_param AS ctp ON cbbi.bill_item_code = ctp.id AND cbbi.location_id = ctp.location_id, care_encounter AS ce
                WHERE (
                cbbi.bill_item_cancel_date >= '$date' AND cbbi.bill_item_cancel_date <= '$toDate'
                ) AND (
                cbbi.location_id = 90
                ) AND cbf.final_encounter_nr = cbbi.bill_item_encounter_nr AND cbbi.bill_nr = cbf.final_bill_no AND cbf.location_id = cbbi.location_id AND ce.encounter_nr = cbbi.bill_item_encounter_nr AND ce.location_id = cbbi.location_id AND ce.encounter_class_nr = 2
                ) AS bb
                ) AS tt
                GROUP BY billdate, ledgername
                ) AS vv
                ORDER BY billdate, ledgername";
                // echo $getData;
                // die();
                $result = $sql32->query($getData);
                $QueryData = array();
                $show = false;
                while($row = $result->fetch_assoc()){
                    $QueryData[] = $row;
                }
                foreach($QueryData as $item)
                {
                    $BillDate = $item['BILLDATE'];
                    $groupValue[$BillDate][] = $item;
                }
                foreach($groupValue as $values)
                {
                    $output = '{"journal_date" : "'.$values[0]['BILLDATE'].'", 
                        "reference_number" : "7355",
                        "journal_type" : "both", 
                        "line_items"  : ['
                    ;
                    $debits = array();
                    $credit = array();
                    foreach($values as $item)
                    {
                        if($item['DEBIT'] != "0" )
                        {
                            array_push($debits, $item['DEBIT']);
                            $output .= '{
                                "account_id": "'.$item['zohoid'].'",
                                "description": "'.str_replace(["\r\n", '"', "'"],"", trim($item['LEDGER NAME'],"'\'")).'",
                                "amount": "'.$item['DEBIT'].'",
                                "debit_or_credit": "debit"
                            },';
                        }
                        if($item['CREDIT'] != "0")
                        {
                            array_push($credit, $item['CREDIT']);
                            $output .= '
                            {
                                "account_id": "'.$item['zohoid'].'",
                                "description": "'.str_replace(["\r\n", '"', "'"],"", trim($item['LEDGER NAME'],"'\'")).'",
                                "amount": "'.$item['CREDIT'].'",
                                "debit_or_credit": "credit"
                            },';
                        }
                    }
                    $c_sum = array_sum($credit);
                    $d_sum = array_sum($debits);
                    
                    if ($c_sum < $d_sum)
                    {
                        $_temp = $d_sum - $c_sum;
                        $output .= '{
                            "account_id": "1434248000000858167",
                            "amount": "'.$_temp.'",
                            "debit_or_credit": "credit"
                        },';
                    }
                    else if ($c_sum > $d_sum)
                    {
                        $_temp = $c_sum - $d_sum ;
                        $output .= '{
                            "account_id": "1434248000000858167",
                            "amount": "'.$_temp.'",
                            "debit_or_credit": "debit"
                        },';
                    }
                    // echo "credit  $c_sum </br>";
                    // echo "debit $d_sum </br>";
                    // echo $_temp;
                    $output = rtrim($output, ',');
                    $output .= ']},';
                    $output = rtrim($output, ',');
                    echo $output;
                    $inDb = "SELECT `journal_date` FROM `zoho_journal` WHERE `journal_date` = '".$values[0]['BILLDATE']."' AND `flag`= '1'" ;
                    $rs = $sql32->query($inDb);
                    while($rest = $rs->fetch_assoc())
                    {
                        $matching = $rest;
                    }
                    if ($matching['journal_date'] == $values[0]['BILLDATE'])
                    {
                        echo '<div class="exists">Op Collection Exists </div>';
                        echo "<style>
                        .exists{
                            position: fixed;
                            top: 0;
                            right: 0;
                            margin-top: 10rem;
                            margin-right: 5rem;
                            border-left: 1px solid red;
                            padding: 0.5rem 2rem;
                            background: #f6f6f6;
                            animation-name : rightTocenter;
                            animation-duration : 2s;
                        }
                        .exists::before{
                            width: 0%;
                            height: 2%;
                            position: absolute;
                            bottom: 0;
                            left: 0;
                            background-color: red;
                            z-index: 1;
                            content: '';
                            transition : all 0.3s ease-in;
                            animation-name : bottomWidth;
                            animation-duration : 6s;
                        }
        
                        @keyframes bottomWidth {
        
                            0%{
                                width : 0%;
                            }
                            100%{
                                width : 100%;
                            }
                        }
                        </style>";
                    }
                    else {
                        if($output == null)
                        {
                            $curl = curl_init();
                            curl_setopt_array($curl, array(
                            CURLOPT_URL => "https://www.zohoapis.in/books/v3/journals?organization_id=$orgId",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => $output,
                            CURLOPT_HTTPHEADER => array(
                                "Authorization: Bearer $access_token",
                                'content-type: application/json'
                            ),
                            ));
                            $response = curl_exec($curl);
                            curl_close($curl);
                            $data = json_decode($response , true);
                            $message = $data['message'];
                            $data = $message;
                            if($message == 'The journal entry has been created.')
                            {
                                $show = true;
                                $FindDate = "SELECT `journal_date` FROM `zoho_journal` WHERE `journal_date` = '".$values[0]['BILLDATE']."' AND `flag`= '0'" ;
                                $rs = $sql32->query($FindDate);
                                while($rest = $rs->fetch_assoc())
                                {
                                    $finaldate = $rest;
                                }
                                if ($finaldate['journal_date'] == $values[0]['BILLDATE'])
                                {
                                    $insert = "UPDATE zoho_journal SET `flag` = '1', `message` = '".str_replace(["\r\n", '"', "'"],"", trim($message,"'\'"))."' , error_msg = 'Error Rectified' WHERE `journal_date` = '".$values[0]['BILLDATE']."'";
                                    $insertResult = $sql32->query($insert);
                                }
                                else
                                {
                                    $insert = "INSERT INTO zoho_journal (`journal_date` , `journal_json` , `message` , `error_msg` , `flag`) VALUES( '".$values[0]['BILLDATE']."' , '".$output."', '".str_replace(["\r\n", '"', "'"],"", trim($message,"'\'"))."' , 'No Error Occured', '1')";
                                    $insertResult = $sql32->query($insert);
                                }
                            }
                            if($message != 'The journal entry has been created.')
                            { 
                                $FindDate = "SELECT `journal_date` FROM `zoho_journal` WHERE `journal_date` = '".$values[0]['BILLDATE']."' AND `flag`= '0'" ;
                                $rs = $sql32->query($FindDate);
                                while($rest = $rs->fetch_assoc())
                                {
                                    $finaldate = $rest;
                                }
                                if ($finaldate['journal_date'] == $values[0]['BILLDATE'])
                                {
                                    $insert = "UPDATE zoho_journal SET error_msg = '".str_replace(["\r\n", '"', "'"],"", trim($message,"'\'"))."'   WHERE `journal_date` = '".$values[0]['BILLDATE']."'";
                                    $insertResult = $sql32->query($insert);
                                }
                                else
                                {
                                    $insert = "INSERT INTO zoho_journal (`journal_date` , `journal_json` , `message` , `error_msg`) VALUES( '".$values[0]['BILLDATE']."' , '".$output."', 'Error Occured','".str_replace(["\r\n", '"', "'"],"", trim($message,"'\'"))."')";
                                    $insertResult = $sql32->query($insert);
                                }
                            }
                        }
                    }
                }  
                $logdata = "SELECT * FROM `zoho_journal`";
                $log_reuslt = $sql134->query($logdata);
                if (mysqli_num_rows($log_reuslt) >= 1){
                    echo '<a class="exists" href="http://172.19.0.30/zohoportal/log.php?fromdate='.$date.'&todate='.$toDate.'&location=his"> Explore the ported Log </a>';
                    echo "<style>
                    a
                    {
                        text-decoration : none;
                        color : black;
                    }
                    .exists{
                        position: fixed;
                        border : none;
                        top: 0;
                        right: 0;
                        margin-top: 18rem;
                        margin-right: 8rem;
                        border-left: 1px solid red;
                        padding: 0.5rem 2rem;
                        background: #f6f6f6;
                        animation-name : rightTocenter;
                        animation-duration : 2s;
                    }
                    .exists::before{
                        width: 0%;
                        height: 2%;
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        background-color: red;
                        z-index: 1;
                        content: '';
                        transition : all 0.3s ease-in;
                        animation-name : bottomWidth;
                        animation-duration : 6s;
                    }
                    .exists:hover{
                        color : red;
                    }
                    @keyframes bottomWidth {
        
                        0%{
                            width : 0%;
                        }
                        100%{
                            width : 100%;
                        }
                    }
                    </style>";
                }
            }
            else{
                echo "location missing";
            }
        }
        else 
        {
            echo "<style>
            .container1 {
                position : fixed;
                z-index : 1;
                width : 100%;
                height : 100vh;
                border-radius : 5px solid;
                display:flex;
                justify-content:center;
                align-items:center;
            }
            .container1 .holder{
                display:flex;
                flex-direction : column-reverse;
                box-shadow:
                0px 0px 5.3px rgba(0, 0, 0, 0.039),
                0px 0px 17.9px rgba(0, 0, 0, 0.059),
                0px 0px 80px rgba(0, 0, 0, 0.09);
                width : 400px;
                height : 150px;
                background-color:white;
                justify-content : center;
                align-items: center;
                animation-name : dialogOpen;
                animation-duration : 0.8s;
            }
            .container1 .holder a {
                margin : 1rem 0;
                text-decoration : none;
                color : black;
                font-size : 18px;
                font-weight : 900;
                text-transform : uppercase;
                transition : all 0.3s ease-in;
            }
            .container1 .holder a:hover{
                color : red;
            }
            
            @keyframes dialogOpen {
                0%{
                    opacity : 0%;
                }
                100%{
                    opacity : 100%;
                }
            }
            </style>" ;
            echo '<div class="container1">
            <div class="holder">
            <a href="http://172.19.0.30/zohoportal/landing.php">Ok</a>
            <p> Please Select less than or equal to 15 days.</p>
            </div>
            </div>';
        }
    }
?>
<!-- Checking the selected options here -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zoho Books | Tally Portal</title>
        <link rel="shortcut icon" href="./assets/nobg-kranium_logo.png" type="image/x-icon">
        <!-- jquery library -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://kit.fontawesome.com/3931ead6a2.js" crossorigin="anonymous"></script>
    </head>
    <body>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@300&display=swap');
            * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Manrope', sans-serif;
            }

            .nav {
            height: 5rem;
            position: fixed;
            width: 100%;
            background-color: #3e9ac1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            }

            .nav .logo {
            width: 10%;
            }

            .nav .logo img {
            margin-left: 8rem;
            width: 70%;
            }

            .nav .nav-items ul {
                list-style: none;
                display: flex;
                justify-content: space-evenly;
                margin-right: 8rem;
            }

            .nav .nav-items ul li a{
                margin : 1rem;
                text-decoration : none;
                font-size : 14px;
                color : white;
                text-transform : uppercase;
                background : transparent;
                padding: 0.5rem 1rem;
                position : relative;
                font-weight: 500;
                letter-spacing: 4px;
            }
            .nav .nav-items ul li a:hover{
                color : #3e9ac1 !important;
            }
            .nav .nav-items ul li a::before{
                width: 0%;
                height: 100%;
                position: absolute;
                top: 0;
                left: 0;
                background-color: white;
                z-index: -1;
                content: "";
                transition: all 0.3s ease-in;
            }    
            .nav .nav-items ul li a:hover::before{
                width : 100%;
            }    

            .nav .bars {
                position : fixed;
                visibility :  hidden;
            }
            .nav .active {
                position : fixed;
                top : 0;
                z-index : -1;
            }

            .nav .active .floating {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
                height: 100%;
                background-color: #3e9ac1;
                position: fixed;
                right: 0;
                z-index: -1;
                margin-right: -86rem;
            }

            .nav .active .floating ul .item {
                position : relative;
                background : transparent ;
                font-size : 18px;
                padding : 0.5rem 1rem;
            }

            .nav .active .floating ul .item::before{
                position : absolute;
                top: 0;
                left : 0;
                width : 0%;
                height : 100%;
                z-index : -1;
                content :""; 
                background-color : white;
                transition : all 0.3s ease-in;
            }
            .nav .active .floating ul .item:hover::before{
                width : 100%;
            }
            .nav .active .floating ul li a:hover{
                color : #3e9ac1 !important;
            }
            .nav .active .floating ul li {
                list-style : none;
            }

            .nav .active .floating ul {
                height : 80%;
                display : flex;
                flex-direction : column;
                align-items : center;
                justify-content : space-evenly;
            }
            .nav .active .floating ul li a {
                font-size : 20px;
                text-transform : uppercase;
                color : white;
                text-decoration : none;
                transition : all 0.3s ease-in;
            }
            
            /* file toggle event class prop's */
            .floatingActive {
                margin-right : 0rem !important;
            }
            
            /* animation loading effect of li in floating */
            .floatingActive ul .item1{
                animation-name : rightTocenter;
                animation-duration : 0.5s;
            }
            .floatingActive ul .item2{
                animation-name : rightTocenter;
                animation-duration : 1s;
            }
            .floatingActive ul .item3{
                animation-name : rightTocenter;
                animation-duration : 1.5s;
            }
            .floatingActive ul .item4{
                animation-name : rightTocenter;
                animation-duration : 2s;
            }
            
            /* animation loading effect of li in floating */

            @keyframes rightTocenter {

                0%{
                    transform :translatex(15rem);
                }
                100%{
                    transform :translatex(0rem);
                }
            }

            /* animation loading effect of li in floating */
            .bar1active{
                transform: rotate(45deg);
                position: absolute;
                right: 0;
                margin-right: 0.2rem;
            }
            .bar2active{
                visibility : hidden !important;
            }

            .bar3active{
                transform: rotate(-45deg);
                width: 100% !important;
                position: absolute;
                margin-right: 0.3rem;
            }
            /* file toggle event class prop's */
            h1 {
            text-align: center;
            font-size: 20px;
            padding-top: 7rem;
            text-transform: uppercase;
            font-weight: 400;
            }

            .container {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            }

            .container form {
                display: flex;
                flex-direction: column;
                justify-content: space-evenly;
                height: 45vh;
                width:40%;
                padding : 1rem 2rem;
                transition: 0.3s ease-in;
            }

            .container form:hover{
                /* border : 1px solid #b5adad; */
                box-shadow:
                0px 2px 5.3px rgba(0, 0, 0, 0.147),
                0px 6.7px 17.9px rgba(0, 0, 0, 0.178),
                0px 30px 80px rgba(0, 0, 0, 0.2);
            }

            .container form .set{
                display: flex;
                align-items : center;
                justify-content : space-between;
            }

            .container form .set select {
                border-radius :inherit;
                padding : 0.3rem 1rem;
                transition: 0.3s ease-in;
            }

            .container form .set select:hover{
                background-color : #3e9ac1;
                color : white;
                border :none;
                box-shadow:
                0px 2px 5.3px rgba(0, 0, 0, 0.147),
                0px 6.7px 17.9px rgba(0, 0, 0, 0.178),
                0px 30px 80px rgba(0, 0, 0, 0.2);
            }

            .container form label {
                text-transform : uppercase;
                font-size : 16px;
                font-weight : 300;
            }

            .container form .submit input {
                width: 100%;
                margin-top : 1rem;
                transition : 0.3s ease-in;
                background-color : white;
            }
            .container form .submit input:hover {
                width: 100%;
                margin-top : 1rem;
                transition : 0.3s ease-in;
                border :none;
                box-shadow:
                0px 2px 5.3px rgba(0, 0, 0, 0.147),
                0px 6.7px 17.9px rgba(0, 0, 0, 0.178),
                0px 30px 80px rgba(0, 0, 0, 0.2);
            }
            .container form input {
            padding: 0.3rem 2rem;
            font-size: 14px;
            text-transform: uppercase;
            background-color: none;
            border : 1px solid black;
            transition: 0.3s ease-in;
            width : 60%;
            }

            .container form select {
                width : 60%;
                border-radius : 5px;
            }

            .container form input:hover {
                border: none;
                color:white;
                background-color: #3e9ac1;
                box-shadow:
                0px 2px 5.3px rgba(0, 0, 0, 0.147),
                0px 6.7px 17.9px rgba(0, 0, 0, 0.178),
                0px 30px 80px rgba(0, 0, 0, 0.2);
            }
            svg {
                position : fixed;
                bottom :0;
                width: 100%;
                z-index: -1;
            }
            .copy{
                color:white;
                position: fixed;
                bottom:0;
                width:100%;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 3rem;
                font-size: 12px;
                font-weight: 100;
                text-transform: uppercase;
            }

            .copy a {
                text-decoration : none ;
                color:white;
            }
            .spinner-holder{
                position : fixed;
                width : 100%;
                height : 100vh;
                background-color: #ffffffc2;
                top: 0;
                margin-top: 5rem;
                display : flex;
                justify-content : center;
                align-items : center;
                visibility : hidden;
            }

            .spinner-active{
                visibility : visible !important;
            }

            .spinner-holder .loader {
                width: 180px; /* control the size */
                aspect-ratio: 8/5;
                --_g: no-repeat radial-gradient(#000 68%,#0000 71%);
                -webkit-mask: var(--_g),var(--_g),var(--_g);
                -webkit-mask-size: 25% 40%;
                background: #3e9ac1;
                animation: load 2s infinite;
            }

            /* animation effect's */
                @keyframes floatingNav {
                    0%{
                        opacity : 10%;
                    }
                    50% {
                        opacity : 50%;
                    }
                    100% {
                        opacity:  100%;
                    }
                }

                @keyframes load {
                    0%    {-webkit-mask-position: 0% 0%  ,50% 0%  ,100% 0%  }
                    16.67%{-webkit-mask-position: 0% 100%,50% 0%  ,100% 0%  }
                    33.33%{-webkit-mask-position: 0% 100%,50% 100%,100% 0%  }
                    50%   {-webkit-mask-position: 0% 100%,50% 100%,100% 100%}
                    66.67%{-webkit-mask-position: 0% 0%  ,50% 100%,100% 100%}
                    83.33%{-webkit-mask-position: 0% 0%  ,50% 0%  ,100% 100%}
                    100%  {-webkit-mask-position: 0% 0%  ,50% 0%  ,100% 0%  }
                }
            /* animation effect's */

            /* responsive design Codes */
            @media (max-width:400px) {

                .nav .logo img{
                    width : 100px;
                    margin-left : 2rem;
                }

                .nav .nav-items{
                    visibility : hidden;
                }

                .nav .bars {
                    position : fixed;
                    top: 0;
                    right : 0;
                    visibility: visible !important;
                    width : 2.5rem;
                    height : 2.5rem;
                    display : flex;
                    flex-direction : column;
                    justify-content : space-evenly;
                    margin :1rem;
                }

                .nav .bars .bar {
                    height : 0.3rem;
                    width : 100%;
                    background-color : white;
                }
                .nav .bars .bar2{
                    width: 70%;
                    transition : 0.3s ease-in;
                }
                .nav .bars .bar3{
                    width: 50%;
                    transition : 0.3s ease-in;
                }

                .nav .bars:hover .bar2, .nav .bars:hover .bar3{
                    width : 100% ;
                }

                h1 {
                    font-size : 18px;
                    padding-top : 8rem;
                }
                .container form{
                    width : 85%;
                    padding: 2rem !important;
                    height : auto;
                }
                .container form .set{
                    display: flex;
                    align-items : center ;
                    margin : 1rem 0;
                }
                .container form .set label{
                    font-size:12px;
                }

                .container form .set input, select {
                    width : 50% !important;
                }

                .copy {
                    height : 1rem;
                }
            }
            @media (max-width:800px) {
                .nav .logo {
                    width: 15%;
                }
                .nav .logo img {
                    margin-left: 5rem;
                    width: 100%;
                }
                .nav .nav-items ul
                {
                    margin-left : 1rem;
                }
                form{
                    width : 80% !important;
                }
            }
            .snackActive {
                position: fixed;
                top: 0;
                right: 0;
                margin-top: 10rem;
                margin-right: 3rem;
                border-left: 1px solid blue;
                padding: 0.5rem 2rem;
                background: #f6f6f6;
                animation-name : rightTocenter;
                animation-duration : 2s;
            }
            .snackActive::before
            {
                width: 0%;
                height: 2%;
                position: absolute;
                bottom: 0;
                left: 0;
                background-color: blue;
                z-index: 1;
                content: '';
                transition : all 0.3s ease-in;
                animation-name : bottomWidth;
                animation-duration : 4s;
            }
            @keyframes bottomWidth {
                0%{
                    width : 0%;
                }
                100%{
                    width : 100%;
                }
            }
        </style> 
        <!-- styling java script -->
        <script>
            function sidebar() {
                var elementSuffixes = {
                    floating: "Active",
                    bar1: "active",
                    bar2: "active",
                    bar3: "active"
                };

                for (var elementId in elementSuffixes) {
                    var element = document.getElementById(elementId);
                    if (element) {
                        var className = elementId + elementSuffixes[elementId];
                        element.classList.toggle(className);
                    }
                }
            }
            
            function showSnack(message){
                if(message)
                {
                    setTimeout(() => {
                        var element = document.getElementById("snack-text");
                        element.innerHTML = message; 
                        element.classList.add("snackActive"); 
                    }, 1000);

                    setTimeout(() => {
                        var element = document.getElementById("snack-text");
                        element.innerHTML = ""; 
                        element.classList.remove("snackActive"); 
                    }, 5000);
                }
            }

            var ZohoMessage = "<?php echo $data; ?>";

            if(ZohoMessage == 'A bill with this number has already been created for this vendor. Please check and try again.')
            {
                let billExists = "Bill Exists";
                showSnack(billExists);
            }else if(ZohoMessage == "The bill has been created."){
                let billExists = "Bill Created";
                showSnack(billExists);
            }else if (ZohoMessage == "The journal entry has been created."){
                showSnack(ZohoMessage);
            }else{
                showSnack(ZohoMessage);
            }

            function spinnerVisible(){
                document.getElementById("spinner-holder").classList.add("spinner-active");
            }
        </script>
        <!-- styling java script -->
        <div class="nav">
        <div class="logo">
        <img src="./assets/reportimage.png" alt="">
        </div>
        <div class="nav-items">
            <ul style = "margin-right: 50px;">
                <!-- <li><a href="" class="item1">Home</a></li> -->
                <li><a href="http://172.19.0.30/zohoportal/assets/show_vendor.php" class="item2">Vendor's Detail</a></li>
                <li><a href="http://172.19.0.30/zohoportal/auth.php" class="item3">Logout <i class="fa-solid fa-right-from-bracket" style="color: #3e9ac1;"></i></a></li>
                <!-- <li><a href="" class="item4">item4</a></li> -->
            </ul>
        </div>
        <div onclick="sidebar()" class="bars" id="bar">
                <span class="bar bar1" id="bar1"></span>
                <span class="bar bar2" id="bar2"></span>
                <span class="bar bar3" id="bar3"></span>
        </div>
        <!-- <div id="active" class="active">
                <div id="floating" class="floating">
                    <ul>
                        <li class="item item1"><a href="" class="values value1">value1</a></li>
                        <li class="item item2"><a href="" class="values value2">value2</a></li>
                        <li class="item item3"><a href="" class="values value3">value3</a></li>
                        <li class="item item4"><a href="" class="values value4">value4</a></li>
                    </ul>
                </div>
        </div> -->
        </div>     
        <h1>Zoho Books | Tally Portal</h1>
        <div class="container">
            <form action="<?php echo $_SERVER["PHP_SELF"] ?>" method="POST">
                <div class="set set1">
                    <label for="fromDate">From date :</label>
                    <input type="date" name="fromDate" id="fromDate">
                </div>
                <div class="set set1">
                    <label for="toDate">To date :</label>
                    <input type="date" name="toDate" id="toDate">
                </div>
                <div class="set set2">
                    <label for="location">Operations :</label>
                    <select name="location" id="location">
                        <option value="pharmacy" class="pharmacy">
                            Pharmacy / Bills
                        </option>
                        <option value="stores" class="stores" >Stores / Bills</option>
                        <option value="his" class="his" >Op Sales Collections</option>
                    </select>
                </div>
                <div class="submit">
                    <input onclick="spinnerVisible()" type="submit" value="Port data" class="submit">
                </div>
            </form>
        </div>
        <div class="copy">
            <p>&copy; Kranium HealthCare System (PVT) LTD All rights reserved -- <a href="https://kraniumhealth.com/">Site</a></p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="#3e9ac1" fill-opacity="1" d="M0,224L48,234.7C96,245,192,267,288,229.3C384,192,480,96,576,101.3C672,107,768,213,864,218.7C960,224,1056,128,1152,90.7C1248,53,1344,75,1392,85.3L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>
        <div id="floatingbar">
            <p id="snack-text"></p>
        </div>
        <div class="spinner-holder" id="spinner-holder">
            <div class="loader"></div>
        </div>
    </body>
</html>
