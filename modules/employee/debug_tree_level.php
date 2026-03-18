<?php
// Simple debug file to test tree level view
echo "DEBUG TEST - Tree Level View<br>";
echo "GET parameters: " . print_r($_GET, true) . "<br>";

$display_type = $_GET['display'] ?? 'tree';
echo "Display type: " . $display_type . "<br>";

if ($display_type === 'tree_level') {
    echo "<h1>TREE LEVEL VIEW WORKING!</h1>";
    echo "<div style='background: green; color: white; padding: 10px;'>Tree level view code is being executed!</div>";
} else {
    echo "<h1>Not tree level view</h1>";
    echo "<div style='background: red; color: white; padding: 10px;'>Current display type: " . $display_type . "</div>";
}

echo "<br><br>";
echo "<a href='?display=tree_level'>Test tree_level</a><br>";
echo "<a href='?display=tree'>Test tree</a><br>";
echo "<a href='?display=location_tree'>Test location_tree</a><br>";
?>