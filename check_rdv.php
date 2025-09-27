<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Rdv;
use App\Models\Annonce;

$rdv = Rdv::find(13);
echo "RDV ID: " . ($rdv ? $rdv->id : 'not found') . PHP_EOL;
echo "Annonce ID: " . ($rdv ? $rdv->annonce_id : 'no rdv') . PHP_EOL;

if ($rdv && $rdv->annonce_id) {
    $annonce = Annonce::find($rdv->annonce_id);
    echo "Annonce found: " . ($annonce ? 'yes' : 'no') . PHP_EOL;
    if ($annonce) {
        echo "Annonce price: " . $annonce->price . PHP_EOL;
        echo "Annonce title: " . $annonce->title . PHP_EOL;
        echo "Annonce is_active: " . ($annonce->is_active ? 'true' : 'false') . PHP_EOL;
    }
} else {
    echo "No annonce_id in RDV record" . PHP_EOL;
}

// Check with relationship
$rdvWithAnnonce = Rdv::with('annonce')->find(13);
echo "RDV with relationship - Annonce loaded: " . ($rdvWithAnnonce && $rdvWithAnnonce->annonce ? 'yes' : 'no') . PHP_EOL;
if ($rdvWithAnnonce && $rdvWithAnnonce->annonce) {
    echo "Relationship price: " . $rdvWithAnnonce->annonce->price . PHP_EOL;
}
