<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Rdv;
use App\Models\Annonce;

// Find RDV 13
$rdv = Rdv::find(13);
if (!$rdv) {
    echo "RDV 13 not found" . PHP_EOL;
    exit;
}

echo "Current RDV 13 data:" . PHP_EOL;
echo "- ID: " . $rdv->id . PHP_EOL;
echo "- Target User ID: " . $rdv->target_user_id . PHP_EOL;
echo "- Annonce ID: " . ($rdv->annonce_id ?: 'NULL') . PHP_EOL;

// Find an active announcement from the same doctor
$announcement = Annonce::where('user_id', $rdv->target_user_id)
    ->where('is_active', 1)
    ->first();

if ($announcement) {
    echo "Found announcement for doctor:" . PHP_EOL;
    echo "- Annonce ID: " . $announcement->id . PHP_EOL;
    echo "- Title: " . $announcement->title . PHP_EOL;
    echo "- Price: " . $announcement->price . PHP_EOL;
    
    // Update RDV with annonce_id
    $rdv->annonce_id = $announcement->id;
    $rdv->save();
    
    echo "Updated RDV 13 with annonce_id: " . $announcement->id . PHP_EOL;
} else {
    echo "No active announcement found for doctor ID: " . $rdv->target_user_id . PHP_EOL;
}
