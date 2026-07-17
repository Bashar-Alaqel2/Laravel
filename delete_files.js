const fs = require('fs');

const files = [
    'd:/projects/laravel/sabapost/app/Http/Controllers/Api/FrequencyPackageController.php',
    'd:/projects/laravel/sabapost/app/Models/FrequencyPackage.php',
    'd:/projects/Digital-Signage-System-main/Digital-Signage-System-main/src/modules/admin/FrequencyPackagesPage.jsx'
];

files.forEach(f => {
    if (fs.existsSync(f)) {
        fs.unlinkSync(f);
        console.log('Deleted ' + f);
    }
});
