<?php
 
header ("Access-Control-Allow-Origin: *");
header ("Access-Control-Expose-Headers: Content-Length, X-JSON");
header ("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
header ("Access-Control-Allow-Headers: *"); 

$conn = new mysqli("localhost", "root", "Lokarda7!", "uptimer");
 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
 
$out = array('error' => false);
 
$crud = 'read';
 
if(isset($_GET['crud'])){
	$crud = $_GET['crud'];
}
 
 
if($crud = 'read'){
	$sql = "select * from servers order by id asc";
	$query = $conn->query($sql);
	$servers = array();
 
	while($row = $query->fetch_array()){
		array_push($servers, $row);
	}
 
	$out['servers'] = $servers;
}
 
if($crud == 'create'){
 
	$id = $_POST['id'];
	$address= $_POST['address'];
	$type = $_POST['type'];
	$findString = $_POST['findString'];
	$timeout = $_POST['timeout'];
 
	$sql = "insert into servers (id, address, type, findString, timeout) values ('$id', '$address', '$type', '$findString', '$timeout')";
	$query = $conn->query($sql);
 
	if($query){
		$out['message'] = "Server Added Successfully";
	}
	else{
		$out['error'] = true;
		$out['message'] = "Could not add Server";
	}
 
} 

if($crud == 'update'){
 
	$id = $_POST['id'];
	$address= $_POST['address'];
	$type = $_POST['type'];
	$findString = $_POST['findString'];
	$timeout = $_POST['timeout'];
 
	$sql = "update servers set address='$address', type='$type', findString='$findString',timeout='$timeout' where memid='$memid'";
	$query = $conn->query($sql);
 
	if($query){
		$out['message'] = "Server Updated Successfully";
	}
	else{
		$out['error'] = true;
		$out['message'] = "Could not update Server";
	}
 
}
 
if($crud == 'delete'){
 
	$id = $_POST['id'];
 
	$sql = "delete from servers where id='$id'";
	$query = $conn->query($sql);
 
	if($query){
		$out['message'] = "Server Deleted Successfully";
	}
	else{
		$out['error'] = true;
		$out['message'] = "Could not delete Server";
	}

} 
 
$conn->close();
 
header("Content-type: application/json");
echo json_encode($out);
die();
 
 
?>