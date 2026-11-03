<?php
// groupd/views/surgeon/api_search_snomed.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

header('Content-Type: application/json');

// Recibir la búsqueda y pasarla a minúsculas
$query = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

// Permitir búsqueda desde 2 caracteres para que sea más ágil
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

// =======================================================================
// SIMULACIÓN DE BASE DE DATOS SNOMED CT (CATÁLOGO AMPLIADO)
// En producción, esto sería una consulta a una API real (ej. Snowstorm)
// =======================================================================
$snomed_db = [
    // --- GENERAL SURGERY / ABDOMINAL ---
    ['id' => '85189001',  'term' => 'Acute appendicitis'],
    ['id' => '92500001',  'term' => 'Cholecystitis'],
    ['id' => '72861006',  'term' => 'Acute cholecystitis'],
    ['id' => '400004000', 'term' => 'Cholelithiasis (Gallstones)'],
    ['id' => '297129007', 'term' => 'Inguinal hernia'],
    ['id' => '26349005',  'term' => 'Umbilical hernia'],
    ['id' => '234222003', 'term' => 'Ventral hernia'],
    ['id' => '39621005',  'term' => 'Incisional hernia'],
    ['id' => '25605004',  'term' => 'Hiatal hernia'],
    ['id' => '80197005',  'term' => 'Diverticulitis of colon'],
    ['id' => '129209007', 'term' => 'Diverticular disease'],
    ['id' => '25983002',  'term' => 'Peritonitis'],
    ['id' => '5193003',   'term' => 'Perforated viscus'],
    ['id' => '400096001', 'term' => 'Small bowel obstruction'],
    ['id' => '26036001',  'term' => 'Obstruction of intestine'],
    ['id' => '302228003', 'term' => 'Adhesions of peritoneum'],
    
    // --- ORTHOPEDICS / TRAUMA ---
    ['id' => '239873007', 'term' => 'Osteoarthritis of knee'],
    ['id' => '239872002', 'term' => 'Osteoarthritis of hip'],
    ['id' => '30283000',  'term' => 'Rheumatoid arthritis'],
    ['id' => '5655000',   'term' => 'Fracture of femur'],
    ['id' => '46866001',  'term' => 'Fracture of lower leg'],
    ['id' => '33173003',  'term' => 'Fracture of tibia'],
    ['id' => '1370007',   'term' => 'Fracture of radius'],
    ['id' => '58150001',  'term' => 'Fracture of clavicle'],
    ['id' => '64663004',  'term' => 'Tear of meniscus of knee'],
    ['id' => '732239002', 'term' => 'Injury of anterior cruciate ligament'],
    ['id' => '209492003', 'term' => 'Rotator cuff syndrome'],
    ['id' => '268023000', 'term' => 'Spinal stenosis'],
    ['id' => '57656006',  'term' => 'Spondylolisthesis'],
    
    // --- ONCOLOGY (CANCER) ---
    ['id' => '109355002', 'term' => 'Carcinoma of breast'],
    ['id' => '254582000', 'term' => 'Carcinoma of colon'],
    ['id' => '363406005', 'term' => 'Malignant tumor of colon'],
    ['id' => '126906006', 'term' => 'Neoplasm of ovary'],
    ['id' => '254637007', 'term' => 'Non-small cell lung cancer'],
    ['id' => '399068003', 'term' => 'Malignant neoplasm of prostate'],
    ['id' => '93655004',  'term' => 'Skin cancer (Melanoma)'],
    ['id' => '363346000', 'term' => 'Malignant tumor of stomach'],
    ['id' => '187766004', 'term' => 'Leukemia'],
    ['id' => '115244002', 'term' => 'Lymphoma'],
    
    // --- CARDIOLOGY / VASCULAR ---
    ['id' => '368009',    'term' => 'Heart valve disorder'],
    ['id' => '414545008', 'term' => 'Ischemic heart disease'],
    ['id' => '22298006',  'term' => 'Myocardial infarction (Heart attack)'],
    ['id' => '49436004',  'term' => 'Atrial fibrillation'],
    ['id' => '60573004',  'term' => 'Aortic valve stenosis'],
    ['id' => '48724000',  'term' => 'Mitral valve regurgitation'],
    ['id' => '84114007',  'term' => 'Heart failure'],
    ['id' => '38341003',  'term' => 'Hypertension'],
    ['id' => '266257000', 'term' => 'Transient ischemic attack'],
    ['id' => '230690007', 'term' => 'Cerebrovascular accident (Stroke)'],
    ['id' => '70602003',  'term' => 'Varicose veins of lower extremity'],
    
    // --- OBGYN ---
    ['id' => '198609003', 'term' => 'Uterine fibroids'],
    ['id' => '29166004',  'term' => 'Ovarian cyst'],
    ['id' => '129103003', 'term' => 'Ectopic pregnancy'],
    ['id' => '61491003',  'term' => 'Prolapse of uterus'],
    ['id' => '396631001', 'term' => 'Endometriosis'],
    
    // --- ENT / NEURO / OTHER ---
    ['id' => '47505003',  'term' => 'Post-traumatic stress disorder'],
    ['id' => '43878008',  'term' => 'Streptococcal pharyngitis'],
    ['id' => '363746003', 'term' => 'Glaucoma'],
    ['id' => '195967001', 'term' => 'Asthma'],
    ['id' => '233604007', 'term' => 'Pneumonia'],
    ['id' => '73211009',  'term' => 'Diabetes mellitus'],
    ['id' => '414916001', 'term' => 'Obesity'],
    ['id' => '128462008', 'term' => 'Secondary malignant neoplasm'],
    ['id' => '95320005',  'term' => 'Disorder of skin'],
    ['id' => '74964007',  'term' => 'Other'],
    ['id' => '444814009', 'term' => 'Viral disease (COVID-19)']
];

// Lógica de búsqueda
$results = [];
$count = 0;

foreach ($snomed_db as $item) {
    // Busca si el texto escrito por el usuario ($query) está dentro del término o del ID
    if (strpos(strtolower($item['term']), $query) !== false || strpos($item['id'], $query) !== false) {
        $results[] = $item;
        $count++;
    }
    // Limitamos a 20 resultados para no hacer la lista infinita
    if ($count >= 20) break;
}

echo json_encode($results);
?>
