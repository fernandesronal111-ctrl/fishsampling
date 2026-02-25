<?php
include "search/connect.php";

echo "<option value=''>Select Location</option>";

if(isset($_GET['species_id'])){

$species_id = (int)$_GET['species_id'];


$sql = "
SELECT DISTINCT l.id, l.location_name
FROM locations l
JOIN visits v ON v.location_id = l.id
JOIN specimens s ON s.visit_id = v.id
WHERE s.species_id = $species_id
";

$result = $conn->query($sql);

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        echo "<option value='{$row['id']}'>{$row['location_name']}</option>";
    }
}else{
    echo "<option value=''>No locations found</option>";
}
}
?>