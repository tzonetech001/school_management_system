<?php
// academic_subjects.php - Academic Combinations & Career Pathways
session_start();
require_once '../controller/db_connect.php';

// Include header
include 'header.php';

// Get all combinations from students table (distinct)
$combinations_sql = "SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination";
$combinations_result = mysqli_query($conn, $combinations_sql);
$combinations = [];
while ($row = mysqli_fetch_assoc($combinations_result)) {
    $combinations[] = $row['combination'];
}

// If no combinations found, define the standard ones
if (empty($combinations)) {
    $combinations = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
}

// Get student counts per combination
$combination_counts = [];
foreach ($combinations as $combo) {
    $count_sql = "SELECT COUNT(*) as count FROM students WHERE combination = '$combo' AND is_leaver = 0 AND status = 1";
    $count_result = mysqli_query($conn, $count_sql);
    $count_data = mysqli_fetch_assoc($count_result);
    $combination_counts[$combo] = $count_data['count'] ?? 0;
}

// Get performance stats per combination (average results from latest exam)
$combination_performance = [];
foreach ($combinations as $combo) {
    // Get latest exam results average for this combination
    $perf_sql = "SELECT AVG(fr.average) as avg_score 
                 FROM form_five_results fr
                 JOIN students s ON fr.student_id = s.id
                 WHERE s.combination = '$combo' AND fr.average IS NOT NULL
                 ORDER BY fr.entered_at DESC LIMIT 100";
    $perf_result = mysqli_query($conn, $perf_sql);
    $perf_data = mysqli_fetch_assoc($perf_result);
    $combination_performance[$combo] = round($perf_data['avg_score'] ?? 0, 1);
}

// Define combination details with subjects and career pathways
$combination_details = [
    'HGE' => [
        'name' => 'History, Geography, Economics',
        'full_name' => 'History, Geography and Economics',
        'subjects' => ['History', 'Geography', 'Economics', 'Kiswahili', 'English'],
        'description' => 'This combination focuses on understanding human societies, their development, economic systems, and geographical landscapes.',
        'icon' => 'fa-landmark',
        'color' => '#3498db',
        'careers' => [
            ['title' => 'Economist', 'description' => 'Analyze economic issues, advise governments and organizations on financial policies', 'salary_range' => 'TZS 2M - 8M/month', 'demand' => 'High'],
            ['title' => 'Urban Planner', 'description' => 'Design and develop city layouts, manage land use and infrastructure projects', 'salary_range' => 'TZS 1.5M - 5M/month', 'demand' => 'Medium-High'],
            ['title' => 'Diplomat', 'description' => 'Represent Tanzania internationally, manage foreign relations and negotiations', 'salary_range' => 'TZS 3M - 10M/month', 'demand' => 'Medium'],
            ['title' => 'Tourism Manager', 'description' => 'Manage tourism operations, develop travel packages, promote cultural heritage', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'High'],
            ['title' => 'Policy Analyst', 'description' => 'Research and analyze government policies, provide recommendations for improvement', 'salary_range' => 'TZS 2M - 6M/month', 'demand' => 'Medium'],
            ['title' => 'GIS Specialist', 'description' => 'Work with geographic information systems for mapping and spatial analysis', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'Growing'],
            ['title' => 'Teacher/Lecturer', 'description' => 'Educate future generations in history, geography, or economics', 'salary_range' => 'TZS 800K - 2.5M/month', 'demand' => 'High']
        ],
        'universities' => ['University of Dar es Salaam', 'Sokoine University of Agriculture', 'Mzumbe University', 'St. Augustine University'],
        'skills' => ['Critical Thinking', 'Research & Analysis', 'Data Interpretation', 'Communication', 'Problem Solving']
    ],
    'HGL' => [
        'name' => 'History, Geography, Language',
        'full_name' => 'History, Geography and Language (French)',
        'subjects' => ['History', 'Geography', 'French', 'Kiswahili', 'English'],
        'description' => 'This combination develops language proficiency alongside historical and geographical knowledge, ideal for international relations.',
        'icon' => 'fa-globe',
        'color' => '#2ecc71',
        'careers' => [
            ['title' => 'Translator/Interpreter', 'description' => 'Translate documents and interpret conversations between English/French and Kiswahili', 'salary_range' => 'TZS 1.5M - 5M/month', 'demand' => 'High'],
            ['title' => 'International Relations Officer', 'description' => 'Work with international organizations like UN, AU, or EAC on cross-border matters', 'salary_range' => 'TZS 3M - 12M/month', 'demand' => 'Medium-High'],
            ['title' => 'Tour Guide', 'description' => 'Lead tours in Tanzania\'s national parks and historical sites for international visitors', 'salary_range' => 'TZS 800K - 3M/month + tips', 'demand' => 'High'],
            ['title' => 'Foreign Service Officer', 'description' => 'Work in embassies, manage visa processing, assist Tanzanian citizens abroad', 'salary_range' => 'TZS 2.5M - 8M/month', 'demand' => 'Medium'],
            ['title' => 'Language Teacher', 'description' => 'Teach French, English, or Kiswahili at schools or language centers', 'salary_range' => 'TZS 800K - 2.5M/month', 'demand' => 'High'],
            ['title' => 'Cultural Heritage Manager', 'description' => 'Preserve and promote cultural sites, manage museum collections', 'salary_range' => 'TZS 1.2M - 3.5M/month', 'demand' => 'Medium'],
            ['title' => 'International Business Coordinator', 'description' => 'Facilitate cross-border trade and communication for companies', 'salary_range' => 'TZS 2M - 6M/month', 'demand' => 'Growing']
        ],
        'universities' => ['University of Dar es Salaam', 'State University of Zanzibar', 'St. Augustine University', 'Institute of Social Work'],
        'skills' => ['Multilingual Communication', 'Cross-cultural Understanding', 'Research', 'Writing', 'Interpersonal Skills']
    ],
    'HGK' => [
        'name' => 'History, Geography, Kiswahili',
        'full_name' => 'History, Geography and Kiswahili',
        'subjects' => ['History', 'Geography', 'Kiswahili', 'English'],
        'description' => 'Focuses on Tanzanian history, geography, and language, preparing students for roles in education, media, and cultural preservation.',
        'icon' => 'fa-book-open',
        'color' => '#e74c3c',
        'careers' => [
            ['title' => 'Journalist', 'description' => 'Report news, write articles for newspapers, TV, radio, or online media', 'salary_range' => 'TZS 1M - 4M/month', 'demand' => 'Medium-High'],
            ['title' => 'Editor', 'description' => 'Review and edit content for publications, maintain quality standards', 'salary_range' => 'TZS 1.5M - 5M/month', 'demand' => 'Medium'],
            ['title' => 'Content Writer', 'description' => 'Create educational content, marketing materials, or online articles', 'salary_range' => 'TZS 800K - 3M/month', 'demand' => 'High'],
            ['title' => 'Archivist', 'description' => 'Preserve historical documents, manage records for government or institutions', 'salary_range' => 'TZS 1M - 3M/month', 'demand' => 'Medium'],
            ['title' => 'Cultural Officer', 'description' => 'Work with Ministry of Culture to preserve and promote Tanzanian heritage', 'salary_range' => 'TZS 1.2M - 3.5M/month', 'demand' => 'Medium'],
            ['title' => 'Teacher', 'description' => 'Teach history, geography, or Kiswahili at secondary schools', 'salary_range' => 'TZS 800K - 2M/month', 'demand' => 'High'],
            ['title' => 'Librarian', 'description' => 'Manage library resources, assist researchers and students', 'salary_range' => 'TZS 800K - 2.5M/month', 'demand' => 'Medium']
        ],
        'universities' => ['University of Dar es Salaam', 'Mzumbe University', 'Dodoma University', 'Institute of Kiswahili Research'],
        'skills' => ['Writing', 'Research', 'Critical Analysis', 'Communication', 'Organization']
    ],
    'HKL' => [
        'name' => 'History, Kiswahili, Language',
        'full_name' => 'History, Kiswahili and Language (French)',
        'subjects' => ['History', 'Kiswahili', 'French', 'English'],
        'description' => 'Combines historical knowledge with language skills in Kiswahili and French for diverse career paths.',
        'icon' => 'fa-language',
        'color' => '#9b59b6',
        'careers' => [
            ['title' => 'Public Relations Officer', 'description' => 'Manage communications between organizations and the public', 'salary_range' => 'TZS 1.5M - 5M/month', 'demand' => 'High'],
            ['title' => 'Community Development Officer', 'description' => 'Work with NGOs or government on community projects and development', 'salary_range' => 'TZS 1.2M - 3.5M/month', 'demand' => 'High'],
            ['title' => 'Social Worker', 'description' => 'Provide support to vulnerable communities, counsel individuals and families', 'salary_range' => 'TZS 800K - 2.5M/month', 'demand' => 'High'],
            ['title' => 'Human Rights Advocate', 'description' => 'Work with legal aid organizations, advocate for marginalized groups', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'Medium'],
            ['title' => 'Broadcast Journalist', 'description' => 'Present news, host talk shows, conduct interviews on TV or radio', 'salary_range' => 'TZS 1.2M - 4M/month', 'demand' => 'Medium'],
            ['title' => 'NGO Program Coordinator', 'description' => 'Manage development programs, coordinate with donors and communities', 'salary_range' => 'TZS 2M - 6M/month', 'demand' => 'Growing'],
            ['title' => 'Researcher', 'description' => 'Conduct social science research for universities or organizations', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'Medium']
        ],
        'universities' => ['University of Dar es Salaam', 'Institute of Social Work', 'Mzumbe University', 'St. Augustine University'],
        'skills' => ['Communication', 'Empathy', 'Research', 'Language Proficiency', 'Community Engagement']
    ],
    'KLF' => [
        'name' => 'Kiswahili, Language, French',
        'full_name' => 'Kiswahili, Language (English) and French',
        'subjects' => ['Kiswahili', 'English', 'French'],
        'description' => 'A language-focused combination preparing students for careers in translation, teaching, and international communication.',
        'icon' => 'fa-comments',
        'color' => '#1abc9c',
        'careers' => [
            ['title' => 'Conference Interpreter', 'description' => 'Provide real-time interpretation at international conferences and meetings', 'salary_range' => 'TZS 3M - 10M/month', 'demand' => 'Medium'],
            ['title' => 'Language Specialist', 'description' => 'Work with localization companies, translate software and websites', 'salary_range' => 'TZS 2M - 6M/month', 'demand' => 'Growing'],
            ['title' => 'ESL Teacher', 'description' => 'Teach English to speakers of other languages in Tanzania or abroad', 'salary_range' => 'TZS 1M - 3M/month', 'demand' => 'High'],
            ['title' => 'Call Center Manager', 'description' => 'Manage multilingual customer service operations for international companies', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'High'],
            ['title' => 'Copywriter', 'description' => 'Create advertising and marketing content in multiple languages', 'salary_range' => 'TZS 1M - 3.5M/month', 'demand' => 'Medium'],
            ['title' => 'Linguist', 'description' => 'Study language structure and development for research institutions', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'Low-Medium'],
            ['title' => 'Diplomatic Aide', 'description' => 'Support embassy operations with language and communication skills', 'salary_range' => 'TZS 2M - 5M/month', 'demand' => 'Medium']
        ],
        'universities' => ['University of Dar es Salaam', 'State University of Zanzibar', 'Institute of Kiswahili Research', 'Open University of Tanzania'],
        'skills' => ['Multilingual Fluency', 'Translation', 'Listening', 'Cultural Awareness', 'Adaptability']
    ],
    'EGM' => [
        'name' => 'Economics, Geography, Mathematics',
        'full_name' => 'Economics, Geography and Mathematics',
        'subjects' => ['Economics', 'Geography', 'Basic Mathematics', 'Advanced Mathematics'],
        'description' => 'A quantitative combination ideal for careers in finance, planning, and data analysis.',
        'icon' => 'fa-chart-line',
        'color' => '#f39c12',
        'careers' => [
            ['title' => 'Statistician', 'description' => 'Collect, analyze, and interpret data for government or private sector', 'salary_range' => 'TZS 2M - 7M/month', 'demand' => 'High'],
            ['title' => 'Financial Analyst', 'description' => 'Analyze investment opportunities, prepare financial reports', 'salary_range' => 'TZS 2.5M - 8M/month', 'demand' => 'High'],
            ['title' => 'Data Scientist', 'description' => 'Use advanced analytics and machine learning to solve business problems', 'salary_range' => 'TZS 3M - 12M/month', 'demand' => 'Very High'],
            ['title' => 'Banker', 'description' => 'Work in commercial banking, investment banking, or microfinance', 'salary_range' => 'TZS 1.5M - 6M/month', 'demand' => 'High'],
            ['title' => 'Economic Planner', 'description' => 'Develop economic development plans for regions or countries', 'salary_range' => 'TZS 2M - 6M/month', 'demand' => 'Medium'],
            ['title' => 'Actuary', 'description' => 'Assess financial risks for insurance companies and pension funds', 'salary_range' => 'TZS 4M - 15M/month', 'demand' => 'Growing'],
            ['title' => 'Supply Chain Analyst', 'description' => 'Optimize logistics and supply chain operations using mathematical models', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'High']
        ],
        'universities' => ['University of Dar es Salaam', 'Mzumbe University', 'Institute of Finance Management', 'Ardhi University'],
        'skills' => ['Mathematical Modeling', 'Data Analysis', 'Statistical Reasoning', 'Critical Thinking', 'Problem Solving']
    ],
    'HLF' => [
        'name' => 'History, Language, French',
        'full_name' => 'History, Language (English) and French',
        'subjects' => ['History', 'English', 'French'],
        'description' => 'Focuses on historical understanding combined with English and French language proficiency.',
        'icon' => 'fa-university',
        'color' => '#e67e22',
        'careers' => [
            ['title' => 'Museum Curator', 'description' => 'Manage museum collections, organize exhibitions, preserve artifacts', 'salary_range' => 'TZS 1.2M - 3.5M/month', 'demand' => 'Low-Medium'],
            ['title' => 'Historical Researcher', 'description' => 'Research historical topics for publications, documentaries, or academic work', 'salary_range' => 'TZS 1M - 3M/month', 'demand' => 'Medium'],
            ['title' => 'Tourism Officer', 'description' => 'Promote tourism destinations, develop tourism policies', 'salary_range' => 'TZS 1.5M - 4M/month', 'demand' => 'Medium-High'],
            ['title' => 'International Marketing', 'description' => 'Develop marketing strategies for international markets', 'salary_range' => 'TZS 2M - 6M/month', 'demand' => 'High'],
            ['title' => 'Legal Assistant', 'description' => 'Support lawyers with research, documentation, and client communication', 'salary_range' => 'TZS 1M - 3M/month', 'demand' => 'Medium'],
            ['title' => 'University Administrator', 'description' => 'Manage academic departments, student affairs, or admissions', 'salary_range' => 'TZS 1.5M - 5M/month', 'demand' => 'Medium'],
            ['title' => 'Foreign Correspondent', 'description' => 'Report international news for media organizations', 'salary_range' => 'TZS 2M - 7M/month', 'demand' => 'Medium']
        ],
        'universities' => ['University of Dar es Salaam', 'St. Augustine University', 'Mzumbe University', 'Institute of Social Work'],
        'skills' => ['Historical Analysis', 'Writing', 'Language', 'Research', 'Cross-cultural Communication']
    ],
    'HGF' => [
        'name' => 'History, Geography, French',
        'full_name' => 'History, Geography and French',
        'subjects' => ['History', 'Geography', 'French', 'English'],
        'description' => 'A comprehensive combination ideal for international careers and diplomacy.',
        'icon' => 'fa-passport',
        'color' => '#c0392b',
        'careers' => [
            ['title' => 'Diplomat', 'description' => 'Represent Tanzania in foreign countries, manage international relations', 'salary_range' => 'TZS 4M - 15M/month', 'demand' => 'Medium'],
            ['title' => 'International Development Specialist', 'description' => 'Work with World Bank, UN, or NGOs on development projects', 'salary_range' => 'TZS 3M - 10M/month', 'demand' => 'High'],
            ['title' => 'Cultural Attaché', 'description' => 'Promote cultural exchange between Tanzania and other nations', 'salary_range' => 'TZS 2.5M - 8M/month', 'demand' => 'Low-Medium'],
            ['title' => 'International Trade Specialist', 'description' => 'Facilitate import/export operations between Tanzania and French-speaking nations', 'salary_range' => 'TZS 2M - 7M/month', 'demand' => 'High'],
            ['title' => 'Aid Coordinator', 'description' => 'Manage humanitarian aid programs for international organizations', 'salary_range' => 'TZS 3M - 9M/month', 'demand' => 'Medium'],
            ['title' => 'Conflict Resolution Specialist', 'description' => 'Work with peacekeeping missions and conflict mediation organizations', 'salary_range' => 'TZS 3M - 12M/month', 'demand' => 'Medium'],
            ['title' => 'International Lawyer', 'description' => 'Practice international law after further legal education', 'salary_range' => 'TZS 5M - 20M/month', 'demand' => 'Medium']
        ],
        'universities' => ['University of Dar es Salaam', 'St. Augustine University', 'Mzumbe University', 'Institute of Diplomatic Studies'],
        'skills' => ['International Relations', 'Diplomacy', 'Language Proficiency', 'Negotiation', 'Cultural Sensitivity']
    ]
];

// Get selected combination for detail view
$selected_combo = isset($_GET['combo']) ? strtoupper($_GET['combo']) : '';
if ($selected_combo && !in_array($selected_combo, $combinations)) {
    $selected_combo = '';
}
$selected_detail = $selected_combo && isset($combination_details[$selected_combo]) ? $combination_details[$selected_combo] : null;
?>

<style>
    /* Academic Subjects Page Styles */
    :root {
        --primary-color: #3B9DB3;
        --primary-dark: #2d7c8f;
        --primary-light: #8bc5d6;
        --accent-color: #ffc107;
        --success-color: #28a745;
        --info-color: #17a2b8;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --dark-color: #2c3e50;
        --light-color: #f8f9fa;
        --gray-color: #6c757d;
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        color: var(--dark-color);
    }
    
    .main-content {
        padding: 100px 0 60px;
    }
    
    /* Hero Section */
    .academic-hero {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        padding: 60px 0;
        margin-bottom: 50px;
        position: relative;
        overflow: hidden;
    }
    
    .academic-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 50px 50px;
        transform: rotate(45deg);
        animation: moveBackground 20s linear infinite;
    }
    
    @keyframes moveBackground {
        0% { transform: rotate(45deg) translate(0, 0); }
        100% { transform: rotate(45deg) translate(50px, 50px); }
    }
    
    .academic-hero .hero-content {
        position: relative;
        z-index: 1;
        text-align: center;
        color: white;
    }
    
    .academic-hero h1 {
        font-size: 48px;
        font-weight: 800;
        margin-bottom: 20px;
    }
    
    .academic-hero p {
        font-size: 18px;
        max-width: 700px;
        margin: 0 auto;
        opacity: 0.95;
    }
    
    .hero-badge {
        background: rgba(255,255,255,0.2);
        padding: 10px 25px;
        border-radius: 50px;
        display: inline-block;
        margin-bottom: 25px;
        font-size: 14px;
        font-weight: 600;
    }
    
    /* Combination Cards Grid */
    .combinations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 25px;
        margin: 40px 0;
    }
    
    .combo-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }
    
    .combo-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(59,157,179,0.2);
    }
    
    .combo-card.active {
        border-color: var(--primary-color);
        background: linear-gradient(135deg, #fff, rgba(59,157,179,0.05));
    }
    
    .combo-card.active::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
    }
    
    .combo-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: white;
        font-size: 30px;
    }
    
    .combo-code {
        font-size: 28px;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 5px;
    }
    
    .combo-name {
        font-size: 14px;
        color: #666;
        margin-bottom: 15px;
    }
    
    .combo-stats {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    
    .combo-stat {
        text-align: center;
    }
    
    .combo-stat-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--dark-color);
    }
    
    .combo-stat-label {
        font-size: 11px;
        color: #999;
    }
    
    /* Detail Section */
    .detail-section {
        background: white;
        border-radius: 30px;
        padding: 40px;
        margin: 40px 0;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    }
    
    .detail-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }
    
    .detail-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 35px;
    }
    
    .detail-title h2 {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 5px;
        color: var(--dark-color);
    }
    
    .detail-title p {
        color: #666;
        margin: 0;
    }
    
    .detail-badge {
        display: inline-block;
        background: rgba(59,157,179,0.1);
        padding: 5px 15px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .subjects-list {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin: 25px 0;
    }
    
    .subject-tag {
        background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
        color: white;
        padding: 8px 20px;
        border-radius: 30px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .description-box {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 20px;
        margin: 25px 0;
        border-left: 4px solid var(--primary-color);
    }
    
    /* Careers Grid */
    .careers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin: 25px 0;
    }
    
    .career-card {
        background: white;
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 16px;
        padding: 20px;
        transition: all 0.3s ease;
    }
    
    .career-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border-color: var(--primary-light);
    }
    
    .career-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 10px;
    }
    
    .career-description {
        font-size: 13px;
        color: #666;
        line-height: 1.6;
        margin-bottom: 12px;
    }
    
    .career-details {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 12px;
    }
    
    .career-salary {
        background: #e8f5e9;
        padding: 4px 12px;
        border-radius: 20px;
        color: #2e7d32;
    }
    
    .career-demand {
        background: #fff3e0;
        padding: 4px 12px;
        border-radius: 20px;
        color: #e65100;
    }
    
    .career-demand.high { background: #e8f5e9; color: #2e7d32; }
    .career-demand.medium { background: #fff3e0; color: #e65100; }
    .career-demand.low { background: #ffebee; color: #c62828; }
    
    /* Skills & Universities */
    .skills-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 20px 0;
    }
    
    .skill-tag {
        background: rgba(59,157,179,0.1);
        padding: 6px 15px;
        border-radius: 30px;
        font-size: 13px;
        color: var(--primary-color);
    }
    
    .university-list {
        list-style: none;
        padding: 0;
        margin: 15px 0;
    }
    
    .university-list li {
        padding: 8px 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .university-list li i {
        color: var(--primary-color);
        width: 20px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 30px;
    }
    
    .empty-state-icon {
        width: 100px;
        height: 100px;
        background: var(--light-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 40px;
        color: var(--primary-color);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .academic-hero h1 { font-size: 36px; }
        .combinations-grid { grid-template-columns: repeat(2, 1fr); }
        .detail-section { padding: 25px; }
        .detail-title h2 { font-size: 24px; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 80px 0 40px; }
        .combinations-grid { grid-template-columns: 1fr; }
        .careers-grid { grid-template-columns: 1fr; }
        .detail-header { flex-direction: column; text-align: center; }
    }
    
    /* Animations */
    .animate-on-scroll {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.6s ease;
    }
    
    .animate-on-scroll.animated {
        opacity: 1;
        transform: translateY(0);
    }
</style>

<!-- Main Content -->
<main class="main-content">
    <!-- Hero Section -->
    <section class="academic-hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-graduation-cap me-2"></i> Academic Programs
                </div>
                <h1>Academic Combinations</h1>
                <p>Explore our diverse subject combinations and discover the career opportunities they unlock</p>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Combinations Grid -->
        <div class="combinations-grid" id="combinationsGrid">
            <?php foreach ($combinations as $combo): 
                $detail = $combination_details[$combo] ?? null;
                $count = $combination_counts[$combo] ?? 0;
                $performance = $combination_performance[$combo] ?? 0;
                $color = $detail ? $detail['color'] : '#3B9DB3';
            ?>
                <div class="combo-card <?php echo $selected_combo == $combo ? 'active' : ''; ?>" 
                     data-combo="<?php echo $combo; ?>"
                     onclick="window.location.href='?combo=<?php echo $combo; ?>'">
                    <div class="combo-icon" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $color; ?>dd);">
                        <i class="fas <?php echo $detail ? $detail['icon'] : 'fa-book'; ?>"></i>
                    </div>
                    <div class="combo-code"><?php echo $combo; ?></div>
                    <div class="combo-name"><?php echo $detail ? $detail['name'] : 'Advanced Level'; ?></div>
                    <div class="combo-stats">
                        <div class="combo-stat">
                            <div class="combo-stat-value"><?php echo $count; ?></div>
                            <div class="combo-stat-label">Students</div>
                        </div>
                        <div class="combo-stat">
                            <div class="combo-stat-value"><?php echo $performance; ?>%</div>
                            <div class="combo-stat-label">Avg Score</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Detail Section (shown when a combination is selected) -->
        <?php if ($selected_combo && $selected_detail): ?>
            <div class="detail-section animate-on-scroll">
                <div class="detail-header">
                    <div class="detail-icon" style="background: linear-gradient(135deg, <?php echo $selected_detail['color']; ?>, <?php echo $selected_detail['color']; ?>dd);">
                        <i class="fas <?php echo $selected_detail['icon']; ?>"></i>
                    </div>
                    <div class="detail-title">
                        <h2><?php echo $selected_combo; ?> - <?php echo $selected_detail['full_name']; ?></h2>
                        <p><?php echo $selected_detail['description']; ?></p>
                    </div>
                </div>

                <!-- Subjects -->
                <h4 class="mt-3"><i class="fas fa-book-open me-2" style="color: var(--primary-color);"></i> Core Subjects</h4>
                <div class="subjects-list">
                    <?php foreach ($selected_detail['subjects'] as $subject): ?>
                        <span class="subject-tag"><?php echo $subject; ?></span>
                    <?php endforeach; ?>
                </div>

                <!-- Description Box -->
                <div class="description-box">
                    <i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>
                    <strong>Why choose this combination?</strong>
                    <p class="mb-0 mt-2"><?php echo $selected_detail['description']; ?> This combination develops critical skills that are highly valued in today's job market, including <?php echo implode(', ', array_slice($selected_detail['skills'], 0, 4)); ?>.</p>
                </div>

                <!-- Career Pathways -->
                <h4 class="mt-4"><i class="fas fa-briefcase me-2" style="color: var(--primary-color);"></i> Career Opportunities</h4>
                <p class="text-muted mb-3">Explore real-world careers available after completing this combination:</p>
                
                <div class="careers-grid">
                    <?php foreach ($selected_detail['careers'] as $career): ?>
                        <div class="career-card">
                            <div class="career-title"><?php echo $career['title']; ?></div>
                            <div class="career-description"><?php echo $career['description']; ?></div>
                            <div class="career-details">
                                <span class="career-salary"><i class="fas fa-money-bill-wave me-1"></i> <?php echo $career['salary_range']; ?></span>
                                <span class="career-demand <?php echo strtolower($career['demand']); ?>">
                                    <i class="fas fa-chart-line me-1"></i> Demand: <?php echo $career['demand']; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Skills Developed -->
                <h4 class="mt-4"><i class="fas fa-brain me-2" style="color: var(--primary-color);"></i> Skills You'll Develop</h4>
                <div class="skills-container">
                    <?php foreach ($selected_detail['skills'] as $skill): ?>
                        <span class="skill-tag"><i class="fas fa-check-circle me-1"></i> <?php echo $skill; ?></span>
                    <?php endforeach; ?>
                </div>

                <!-- Universities -->
                <h4 class="mt-4"><i class="fas fa-university me-2" style="color: var(--primary-color);"></i> Recommended Universities</h4>
                <ul class="university-list">
                    <?php foreach ($selected_detail['universities'] as $uni): ?>
                        <li><i class="fas fa-graduation-cap"></i> <?php echo $uni; ?></li>
                    <?php endforeach; ?>
                </ul>

                <!-- Quick Stats Summary -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 text-center">
                            <i class="fas fa-users fa-2x mb-2" style="color: var(--primary-color);"></i>
                            <h5><?php echo $combination_counts[$selected_combo] ?? 0; ?> Students</h5>
                            <small class="text-muted">Currently enrolled</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 text-center">
                            <i class="fas fa-chart-line fa-2x mb-2" style="color: var(--primary-color);"></i>
                            <h5><?php echo $combination_performance[$selected_combo] ?? 0; ?>%</h5>
                            <small class="text-muted">Average exam score</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 text-center">
                            <i class="fas fa-briefcase fa-2x mb-2" style="color: var(--primary-color);"></i>
                            <h5><?php echo count($selected_detail['careers']); ?>+ Careers</h5>
                            <small class="text-muted">Career pathways</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (!$selected_combo): ?>
            <!-- Prompt to select a combination -->
            <div class="empty-state animate-on-scroll">
                <div class="empty-state-icon">
                    <i class="fas fa-hand-pointer"></i>
                </div>
                <h4>Select a Combination</h4>
                <p class="text-muted">Click on any combination card above to view detailed information about subjects, career opportunities, and university pathways.</p>
            </div>
        <?php endif; ?>

        <!-- Note about subject selection -->
        <div class="alert alert-info mt-4 rounded-4" style="background: rgba(59,157,179,0.1); border: none;">
            <i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>
            <strong>Note:</strong> Subject combinations are subject to change based on curriculum updates from NECTA and Ministry of Education. Students are advised to consult with the Academic Office for the most current information.
        </div>
    </div>
</main>

<script>
// Animation on scroll
document.addEventListener('DOMContentLoaded', function() {
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
            }
        });
    }, { threshold: 0.1 });
    
    animateElements.forEach(element => {
        observer.observe(element);
    });
});
</script>

<?php include '../controller/footer.php'; ?>