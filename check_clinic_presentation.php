<?php
// Script to check clinic_presentation field in clinique_profiles table

// Database configuration (adjust as needed)
$host = 'localhost';
$dbname = 'visante';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if clinic_presentation field exists and has data
    $stmt = $pdo->prepare("SELECT id, user_id, nom_clinique, clinic_presentation FROM clinique_profiles WHERE clinic_presentation IS NOT NULL AND clinic_presentation != '' LIMIT 5");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Clinic profiles with clinic_presentation data:\n";
    echo "=============================================\n";
    foreach ($results as $row) {
        echo "ID: " . $row['id'] . "\n";
        echo "User ID: " . $row['user_id'] . "\n";
        echo "Nom Clinique: " . $row['nom_clinique'] . "\n";
        echo "Clinic Presentation: " . $row['clinic_presentation'] . "\n";
        echo "---------------------------------------------\n";
    }
    
    // Check if there are any clinic profiles
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clinique_profiles");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total clinic profiles in database: " . $count['count'] . "\n";
    
    // Check if clinic_presentation field exists in table structure
    $stmt = $pdo->prepare("DESCRIBE clinique_profiles");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasClinicPresentation = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'clinic_presentation') {
            $hasClinicPresentation = true;
            break;
        }
    }
    
    echo "Clinic presentation field exists: " . ($hasClinicPresentation ? "YES" : "NO") . "\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
