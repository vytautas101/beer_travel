<!DOCTYPE html>
<html>
<body>

<h1>Brewery traveller</h1>

<form action="brewery_travel.php" method="GET">
Latitude: <input name="lat" type="text" />
Longitude: <input name="long" type="text"><br/>
<input type="submit">
</form>

<?php
class TSP {
	
	private $breweries = array();

	function distance($lat1, $lon1, $lat2, $lon2) { 
		if ($lat1 == $lat2 && $lon1 == $lon2) return 0;
		$unit = 'K';	// miles please!
		$theta = $lon1 - $lon2; 
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)); 
		$dist = acos($dist); 
		$dist = rad2deg($dist); 
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);


		if ($unit == "K") {
			return ($miles * 1.609344); 
		} else if ($unit == "N") {
			return ($miles * 0.8684);
		} else {
			return $miles;
		}
	}
	
	public function update_beer_count($geocode_id){
		
		$con = new mysqli("localhost","root","","brewery");
		
		if ($con->connect_error){
			die("Connection error: ". $con->connect_error);
		}
		
		$query = "SELECT COUNT(*) rezultatas from geocodes g, brewery b, beers br where g.brewery_id=b.id and br.brewery_id=b.id and g.id=".$geocode_id ." group by g.id, g.brewery_id, g.latitude, g.longitude";
		
		$rez = $con->query($query);
		
		if($rez->num_rows > 0){
			while($row = $rez->fetch_assoc()){
				//echo "Rasta viso vietoje ".$geocode_id ." buvo ".$row["rezultatas"];
				if ($con->query("UPDATE geocodes set beers=".$row["rezultatas"]." where id=".$geocode_id) === TRUE) {
					echo "Updated! <br/>";
				} else {
					echo "Failed to update record ".$geocode_id;
				}
				
			}	
		} else {
			echo "No beer types found in this location";
		}
	}
	
	public function beers(){
		
		$conn = new mysqli("localhost","root","","brewery");
		
		if ($conn->connect_error){
			die("Connection failed: " . $conn->connect_error);
		}
		
		$sql = "SELECT * FROM geocodes";
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0){
			while($row = $result->fetch_assoc()){
				$this->update_beer_count($row["id"]);
			}
		} else {
			echo "0 results";
		}
		
		$conn->close();
	}
	
	public function locations(){
		
		$i=0;
		$total=0;
		$prems =array();
		$dist = -1;
		$current = array();
		$home = array();
		$cnt = 0;
		$dd = 0;
		$can_travel = true;
		$minLocation = array();
		
		if (empty($_GET["long"]) || empty($_GET["lat"])){
			echo "Please enter coordinates above to start looking for most beer types in 2000km range";
			return;
		}
		
		if (!preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?);[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $_GET["long"].";".$_GET["lat"])){
			echo "Bad format of entered coordinates, please enter coordinates in ranges 90;180 -90;-180";
			return;
		}
		
		$name = "";
		
		$conn = new mysqli("localhost","root","","brewery");
		
		if($conn->connect_error){
			die("Connectino failed: ".$conn->connect_error);
		}
		
		$sql = "SELECT * FROM geocodes";
		
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0){
			while($row = $result->fetch_assoc()){
				$breweries[$i]["id"]=$row["id"];
				$breweries[$i]["latitude"]=$row["latitude"];
				$breweries[$i]["longitude"]=$row["longitude"];
				$i++;
			}
		}else {
			echo "No location found in a database";
		}
		
		$current["id"] = 0;
		$current["latitude"] = $_GET["lat"];//51.355468;
		$current["longitude"] = $_GET["long"];//11.100790; 
		
		$home["id"] = 0;
		$home["latitude"] = $_GET["lat"];//51.355468;
		$home["longitude"] = $_GET["long"];//11.100790; 
		
		echo "<br/>-> HOME: ".$current["latitude"].", ".$current["longitude"]." distance ".$dd."km <br/>";
		
		
		while ($can_travel){
			$dd=0;
			$dist=-1;
		
			foreach	($breweries as $brew){
				
				if(in_array($brew["id"],$prems)){
					continue;
				}
				
				$dd = $this->distance($current["latitude"], $current["longitude"], $brew["latitude"],$brew["longitude"]);
				
				if ($dist == -1 or ($dd<$dist && $dd!=0)){
					$dist=$dd;
					$minLocation[0]["id"] = $brew["id"];
					$minLocation[0]["latitude"]= $brew["latitude"];
					$minLocation[0]["longitude"] = $brew["longitude"];
					
				}
			}
			
			$dd=$this->distance($minLocation[0]["latitude"],$minLocation[0]["longitude"], $home["latitude"],$home["longitude"]);
			
			if ($dist>0 && ($total+$dist+$dd)<=2000){
				$total=$total+$dist;
				$cnt++;
				$prems[$cnt]=$minLocation[0]["id"];
				
				$current["id"]=$minLocation[0]["id"];
				$current["latitude"]=$minLocation[0]["latitude"];
				$current["longitude"]=$minLocation[0]["longitude"];
				
				//$r = $conn->query("SELECT b.name,a.latitude, a.longitude from geocodes a, brewery b where a.brewery=b.id and a.id=".$prems[$cnt]);
			
				$sql = "SELECT b.name,a.latitude, a.longitude from geocodes a, brewery b where a.brewery_id=b.id and a.id=".$prems[$cnt];
		
				$result = $conn->query($sql);
				
				if ($result->num_rows > 0){
					while($row = $result->fetch_assoc()){
						$name = $row["name"];
					}
				}else {
					echo "Location ".$prems[$cnt]." was not found in a databse.";
				}
				
				
				echo "-> [".$current["id"]."] ".$name.": ".$current["latitude"].", ".$current["longitude"]." distance ".$dd."km <br/>";
			}
			else if (($total+$dist)>2000){
				$can_travel=false;
			}else{
				$can_travel=false;
			}
		}
		
		//Pridedant atstuma kiek liko gryzti is esamos vietos iki namu.
		$dd=$this->distance($current["latitude"],$current["longitude"], $home["latitude"],$home["longitude"]);

		$total=$total+$dd;
		
		echo "<- HOME: ".$home["latitude"].", ".$home["longitude"]." distance ".$dd."km <br/><br/>";
		
		
		echo "Total brewery factors found ".count($prems).", total distance travelled: ".$total;
		
		
		
		$sql = "SELECT b.name,a.latitude, a.longitude from geocodes a, brewery b where a.brewery_id=b.id and a.id=".$prems[$cnt];
		
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0){
			while($row = $result->fetch_assoc()){
				$name = $row["name"];
			}
		}else {
			echo "Location ".$prems[$cnt]." was not found in a databse.";
		}
			
	}
	
}

$tsp = new TSP;

$tsp->locations();
?>

</body>
</html>