<?php
// kranium vendor creation files
require_once('.././config.php');
require_once('.././generate_access_key.php');
$findvendor = "SELECT * FROM suppliers WHERE zohoid is Null";
$result = $sql32p->query($findvendor);
while($row = $result->fetch_assoc()){
    $QueryData[] = $row;
}
$address = "";
foreach($QueryData as $data)
{
    $supplierid = $data['supplierid'];
    $address .= $data['address1'];
    $address .= ',';
    $address .= $data['address2'];
    $address .= ',';
    $address .= $data['address3'];
    $address .= ',';
    $address .= $data['address4'];
    $address .= ',';
    $address .= $data['address5'];
    $address .= ',';
    $address .= $data['address6'];
    $address = rtrim($address, ',');
    $output = '{
        "contact_name": "'.$data['suppname'].'",
        "company_name": "'.$data['suppname'].'",
        "contact_type": "vendor",
        "customer_sub_type": "business",
        "gst_no": "'.$data['supGST_NO'].'",
        "gst_treatment": "business_gst",
        "billing_address": {
            "attention": "'.$data['suppname'].'",
            "address": "'.$address.'",
            "country": "India",
        }
    }';
    $address = "";
    $vendor_object = create_vendor($orgId, $access_token, $output);
    if ($vendor_object)
    {
        $created_id = $vendor_object['contact']['contact_id'];
        $message = $vendor_object['message'];
        $contact_name = $vendor_object['contact']['contact_name'];
        $insert = "INSERT INTO `track_zoho_vendor` (`zohoid`, `company_name`,`response_msg`) VALUES($created_id,'$contact_name','$message')";
        $result = $sql32p->query($insert);
        $update_vendor = "UPDATE `suppliers` SET `zohoid` = $created_id WHERE supplierid = '".$supplierid."'";
        $update_vendor_to_db = $sql32p->query($update_vendor);
    }
}
function create_vendor($orgId, $access_token, $output)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_URL => "https://www.zohoapis.in/books/v3/contacts?organization_id=$orgId",
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
    $response = json_decode($response, true);
    return $response;
}
?>
