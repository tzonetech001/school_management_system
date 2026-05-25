Grade A  (80-100): Point 1
Grade B  (70-79):  Point 2
Grade C  (60-69):  Point 3
Grade D  (50-59):  Point 4
Grade E  (40-49):  Point 5
Grade S  (35-39):  Point 6
Grade F  (0-34):   Point 7

4. DIVISION (kwa jumla ya points):
Division I: 3 - 9 points

Division II: 10 - 12 points

Division III: 13 - 17 points

Division IV: 18 - 19 points

Division 0: 20 - 21 points

academic/
├── results_entry.php     (Main form - Form Five)
├── results_entry_six.php (Main form - Form Six)
├── view_results.php      (Kuona matokeo yote)
├── ajax_save_result.php  (Auto-save kwa AJAX)
├── get_student_results.php (Kupata data ya mwanafunzi)
└── database/
    └── results_tables.sql (Schema za matokeo)

Division I: 3 - 9 'Excellent'

Division II: 10 - 12 'Good'

Division III: 13 - 17 'Satisfactory'

Division IV: 18 - 19 'Fail'

Division 0: 20 - 21 'Fail'

subjects are
ac   acadmic communication 
htm    historia ya tanzania na maarifa 
bm    Basic Maths
geo    geography
his     history
lang  english language 
eco     economics
kis   kiswahili
fren  french 
am   advanvced mathematics

ac and htm are composory to eacj combination


$combination_map = [
    'HGE' => ['history', 'geography', 'economics'],
    'HGL' => ['history', 'geography', 'english'],
    'HGK' => ['history', 'geography', 'kiswahili'],
    'HKL' => ['history', 'kiswahili', 'english'],
    'KLF' => ['kiswahili', 'english', 'french'],
    'EGM' => ['geography', 'advanced_maths', 'economics'],
    'HLF' => ['history', 'english', 'french'],
    'HGF' => ['history', 'geography', 'french']
// Combination subjects mapping (for Form Six)
$combination_subjects = [
    'HGE' => ['ac', 'htm', 'his', 'geo', 'b_math', 'eco'],
    'HGL' => ['ac', 'htm', 'his', 'geo', 'eng'],
    'HGK' => ['ac', 'htm', 'his', 'geo', 'kisw'],
    'HKL' => ['ac', 'htm', 'his', 'kisw', 'eng'],
    'KLF' => ['ac', 'htm', 'kisw', 'eng', 'fren'],
    'EGM' => ['ac', 'htm', 'geo', 'adv_m', 'eco'],
    'HLF' => ['ac', 'htm', 'his', 'eng', 'fren'],
    'HGF' => ['ac', 'htm', 'his', 'geo', 'fren']
];

// Subject display names
$subject_display = [
    'ac' => 'AC',
    'htm' => 'HTM',
    'his' => 'HIST',
    'geo' => 'GEO',
    'kisw' => 'KISW',
    'eng' => 'ENG',
    'b_math' => 'B/MATH',
    'adv_m' => 'ADV/M',
    'eco' => 'ECO',
    'fren' => 'FREN'
];

// Subject availability by combination
$subject_availability = [
    'ac' => ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'],
    'htm' => ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'],
    'his' => ['HGE', 'HGL', 'HGK', 'HKL', 'HLF', 'HGF'],
    'geo' => ['HGE', 'HGL', 'HGK', 'EGM', 'HGF'],
    'kisw' => ['HGK', 'HKL', 'KLF'],
    'eng' => ['HGL', 'HKL', 'KLF', 'HLF'],
    'eco' => ['HGE', 'EGM'],
    'b_math' => ['HGE'],
    'adv_m' => ['EGM'],
    'fren' => ['KLF', 'HLF', 'HGF']
];