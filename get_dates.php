<?php
include "search/connect.php";

echo "<option value=''>Select Date</option>";

if(isset($_GET['species']) && isset($_GET['location']) && isset($_GET['month'])){

$species  = (int)$_GET['species'];
$location = (int)$_GET['location'];
$month    = $_GET['month'];

$sql = "
SELECT DISTINCT v.visit_date
FROM visits v
JOIN specimens s ON s.visit_id = v.id
WHERE s.species_id = $species
AND v.location_id = $location
AND MONTHNAME(v.visit_date) = '$month'
ORDER BY v.visit_date
";

$res = $conn->query($sql);

while($row=$res->fetch_assoc()){
    echo "<option value='{$row['visit_date']}'>{$row['visit_date']}</option>";
}
}
?>