<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Rdv;
use App\Models\Annonce;

$rdv = Rdv::find(12);
echo "RDV 12 data:" . PHP_EOL;
echo "ID: " . ($rdv ? $rdv->id : 'not found') . PHP_EOL;
echo "Annonce ID: " . ($rdv ? ($rdv->annonce_id ?: 'NULL') : 'no rdv') . PHP_EOL;
echo "Target User ID: " . ($rdv ? $rdv->target_user_id : 'no rdv') . PHP_EOL;

if ($rdv && !$rdv->annonce_id) {
    // Find an active announcement from the same doctor
    $announcement = Annonce::where('user_id', $rdv->target_user_id)
        ->where('is_active', 1)
        ->first();
    
    if ($announcement) {
        echo "Found announcement for doctor:" . PHP_EOL;
        echo "- Annonce ID: " . $announcement->id . PHP_EOL;
        echo "- Title: " . $announcement->title . PHP_EOL;
        echo "- Price: " . $announcement->price . PHP_EOL;
        echo "- Discount: " . ($announcement->pourcentage_reduction ?: '0') . "%" . PHP_EOL;
        
        // Update RDV with annonce_id
        $rdv->annonce_id = $announcement->id;
        $rdv->save();
        
        echo "Updated RDV 12 with annonce_id: " . $announcement->id . PHP_EOL;
    } else {
        echo "No active announcement found for doctor ID: " . $rdv->target_user_id . PHP_EOL;
    }
} else {
    echo "RDV 12 already has annonce_id: " . $rdv->annonce_id . PHP_EOL;
}
