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
	
	public function locations(){
		
		$i=0;
		$total=0;	//Total distance travelled
		$prems =array(); // Array of all visited locations
		$dist = -1;		 // Min distance to travel from current location to next
		$current = array(); // Current location
		$home = array(); // Home location
		$cnt = 0; // Array counter for visited locations
		$dd = 0;  // Distances
		$can_travel = true; // Check if still have fuel to travel
		$minLocation = array(); //Found min distance to travel location.
		
		if (empty($_GET["long"]) || empty($_GET["lat"])){
			echo "Please enter coordinates above to start looking for most beer types in 2000km range";
			return;
		}
		
		if (!preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?);[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $_GET["long"].";".$_GET["lat"])){
			echo "Bad format of entered coordinates, please enter coordinates in ranges 90;180 -90;-180";
			return;
		}
		
		$name = ""; // Temporary variable for brewery name
		
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
				
				// Check if location was visited already 
				if(in_array($brew["id"],$prems)){
					continue;
				}
				
				// Calculate distance from current location to another
				$dd = $this->distance($current["latitude"], $current["longitude"], $brew["latitude"],$brew["longitude"]);
				
				// Find next closest brewery factor to travel
				if ($dist == -1 or ($dd<$dist && $dd!=0)){
					$dist=$dd;
					$minLocation[0]["id"] = $brew["id"];
					$minLocation[0]["latitude"]= $brew["latitude"];
					$minLocation[0]["longitude"] = $brew["longitude"];
					
				}
			}
			// Need to check distance to travel back home from next brewery factor
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
				
				//Print travelling route
				echo "-> [".$current["id"]."] ".$name.": ".$current["latitude"].", ".$current["longitude"]." distance ".$dd."km <br/>";
			}else{
				$can_travel=false;
			}
		}
		
		//Distance to get back home
		$dd=$this->distance($current["latitude"],$current["longitude"], $home["latitude"],$home["longitude"]);

		$total=$total+$dd;
		
		echo "<- HOME: ".$home["latitude"].", ".$home["longitude"]." distance ".$dd."km <br/><br/>";
		
		if (count($prems)<1){
			echo "You need to move to another location, because there are no brewery factors in 2000km radius";
			return;
		}
		
		
		echo "Total brewery factors found ".count($prems).", total distance travelled: ".$total."km";
		
		echo "<br/><br/><b>Beer types gathered: </b><BR/>";
		
		$sql = "SELECT distinct b.name from geocodes a, beers b where a.brewery_id=b.brewery_id and a.id in (".implode(',',$prems).")";
		
		$result = $conn->query($sql);
		
		//Printing different beer types found in visited brewery factors.
		if ($result->num_rows > 0){
			while($row = $result->fetch_assoc()){
				echo $row["name"]."<br/>";
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