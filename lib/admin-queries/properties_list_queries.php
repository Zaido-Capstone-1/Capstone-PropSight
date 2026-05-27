<?php
/**
 * lib/admin/properties_list_data.php
 * Data layer for pages/admin/properties_list.php
 * Requires: $conn (mysqli)
 */

// Prepare once; re-bind inside the while($row = mysqli_fetch_assoc($result)) loop
$unitStmt = $conn->prepare(
    "SELECT COUNT(*) AS total_units,
            COALESCE(SUM(status='occupied'),0) AS occupied_units
     FROM units WHERE property_id=?"
);