<?php
$page_title = 'ຄູ່ມືການໃຊ້ງານ - ລະບົບຈັດການວັດ';
require_once 'config/db.php';
require_once 'config/base_url.php';
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ຄູ່ມືການໃຊ້ງານ - ລະບົບຈັດການວັດ</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fcf9f2',
                            100: '#f8f0db',
                            200: '#f0deb1',
                            300: '#e7c782',
                            400: '#dfb45d',
                            500: '#d4a762',
                            600: '#b08542',
                            700: '#8e6b35',
                            800: '#6d521f',
                            900: '#4b3612',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/about-style.css">
</head>
<body class="bg-primary-50">

    <header class="bg-gradient-to-r from-primary-700 to-primary-600 text-white shadow-md">
        <div class="section-container py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="assets/images/logo.png" alt="Logo ລະບົບຈັດການວັດ" class="h-14 w-auto">
                    <div class="ml-4">
                        <h1 class="text-3xl font-bold">ລະບົບຈັດການວັດ</h1>
                        <p class="text-primary-200">ຄູ່ມືການໃຊ້ງານສຳລັບຜູ້ໃຊ້</p>
                    </div>
                </div>
                <nav class="hidden md:block">
                    <ul class="flex space-x-6">
                        <li><a href="index.php" class="hover:text-primary-200 transition-colors">ໜ້າຫຼັກ</a></li>
                        <li><a href="#features" class="hover:text-primary-200 transition-colors">ຄຸນສົມບັດ</a></li>
                        <li><a href="#contact" class="hover:text-primary-200 transition-colors">ຕິດຕໍ່</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <div class="hero-pattern bg-primary-100 py-12">
        <div class="section-container">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-4xl font-bold text-primary-800 mb-6">ຄູ່ມືການໃຊ້ງານລະບົບຈັດການວັດ</h2>
                <p class="text-lg text-gray-600 mb-8">ຍິນດີຕ້ອນຮັບສູ່ຄູ່ມືການໃຊ້ງານລະບົບຈັດການວັດອອນໄລນ໌. ຄູ່ມືນີ້ຈະຊ່ວຍໃຫ້ທ່ານເຂົ້າໃຈແລະໃຊ້ງານລະບົບໄດ້ຢ່າງເຕັມປະສິດທິພາບ.</p>
                <div class="flex justify-center">
                    <a href="<?= $base_url ?>auth/" class="bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition-all transform hover:-translate-y-1 hover:shadow-xl">
                        ເລີ່ມຕົ້ນໃຊ້ງານ <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white py-8">
        <div class="section-container">
            <div class="flex flex-wrap -mx-4">
                <div class="w-full md:w-1/4 px-4 mb-8 md:mb-0">
                    <div class="sticky top-8">
                        <h3 class="text-xl font-bold text-primary-700 mb-4">ສາລະບານ</h3>
                        <nav class="toc">
                            <ul class="space-y-2">
                                <li>
                                    <a href="#login" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-sign-in-alt text-primary-600 text-sm"></i>
                                        </div>
                                        ການເຂົ້າສູ່ລະບົບ
                                    </a>
                                </li>
                                <li>
                                    <a href="#users" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-users text-primary-600 text-sm"></i>
                                        </div>
                                        ການຈັດການຜູ້ໃຊ້ງານ
                                    </a>
                                </li>
                                <li>
                                    <a href="#temples" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-gopuram text-primary-600 text-sm"></i>
                                        </div>
                                        ການຈັດການຂໍ້ມູນວັດ
                                    </a>
                                </li>
                                <li>
                                    <a href="#events" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-calendar-alt text-primary-600 text-sm"></i>
                                        </div>
                                        ຈັດການກິດຈະກຳ
                                    </a>
                                </li>
                                <li>
                                    <a href="#donations" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-hand-holding-heart text-primary-600 text-sm"></i>
                                        </div>
                                        ຈັດການການບໍລິຈາກ
                                    </a>
                                </li>
                                <li>
                                    <a href="#settings" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-cog text-primary-600 text-sm"></i>
                                        </div>
                                        ການຕັ້ງຄ່າລະບົບ
                                    </a>
                                </li>
                                <li>
                                    <a href="#faq" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-question text-primary-600 text-sm"></i>
                                        </div>
                                        ຄຳຖາມທີ່ພົບເລື້ອຍ
                                    </a>
                                </li>
                                <li>
                                    <a href="#contact" class="flex items-center text-gray-600 hover:text-primary-600 py-1">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center mr-2">
                                            <i class="fas fa-phone-alt text-primary-600 text-sm"></i>
                                        </div>
                                        ຕິດຕໍ່ພວກເຮົາ
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                
                <div class="w-full md:w-3/4 px-4">
                    <!-- ການເຂົ້າສູ່ລະບົບ -->
                    <section id="login" class="manual-section">
                        <h2 class="text-2xl font-bold text-primary-800 mb-6 header-underline">
                            <i class="fas fa-sign-in-alt mr-2 text-primary-600"></i> ການເຂົ້າສູ່ລະບົບ
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="card">
                                <h3 class="text-lg font-semibold text-primary-700 mb-3">ການເຂົ້າສູ່ລະບົບ</h3>
                                <ol class="list-decimal pl-5 space-y-2">
                                    <li>ເຂົ້າເວັບໄຊທ໌: <span class="text-primary-600 font-medium">https://yourdomain.com/temples</span></li>
                                    <li>ໃສ່ຊື່ຜູ້ໃຊ້ແລະລະຫັດຜ່ານຂອງທ່ານ</li>
                                    <li>ກົດປຸ່ມ "ເຂົ້າສູ່ລະບົບ"</li>
                                </ol>
                                <div class="mt-4 text-gray-500">
                                    <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> ເພື່ອຄວາມປອດໄພ, ລະບົບຈະອອກຈາກລະບົບໂດຍອັດຕະໂນມັດຫາກບໍ່ມີການໃຊ້ງານເປັນເວລາ 30 ນາທີ.</p>
                                </div>
                            </div>
                            
                            <div class="card">
                                <h3 class="text-lg font-semibold text-primary-700 mb-3">ລືມລະຫັດຜ່ານ</h3>
                                <ol class="list-decimal pl-5 space-y-2">
                                    <li>ຄລິກ "ລືມລະຫັດຜ່ານ" ໃນໜ້າເຂົ້າສູ່ລະບົບ</li>
                                    <li>ເລືອກຮູບແບບການກູ້ຄືນ (Email ຫຼື SMS)</li>
                                    <li>ປ້ອນອີເມວ ຫຼື ເບີໂທລະສັບທີ່ລົງທະບຽນ</li>
                                    <li>ປະຕິບັດຕາມຄຳແນະນຳທີ່ໄດ້ຮັບ</li>
                                </ol>
                                <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-3 text-sm text-yellow-700">
                                    <p><strong>ໝາຍເຫດ:</strong> OTP ສຳລັບການກູ້ຄືນລະຫັດຜ່ານຈະໝົດອາຍຸພາຍໃນ 15 ນາທີ.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-xl p-5 mb-4">
                            <h3 class="text-lg font-semibold text-gray-700 mb-3">ການປ້ອງກັນຄວາມປອດໄພ</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-start">
                                    <div class="bg-primary-100 p-2 rounded-full mr-3">
                                        <i class="fas fa-shield-alt text-primary-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">ຕັ້ງລະຫັດຜ່ານທີ່ເຂັ້ມແຂງ</h4>
                                        <p class="text-sm text-gray-600">ໃຊ້ຕົວອັກສອນ, ຕົວເລກ, ແລະສັນຍາລັກພິເສດ</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="bg-primary-100 p-2 rounded-full mr-3">
                                        <i class="fas fa-user-lock text-primary-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">ອອກຈາກລະບົບທຸກຄັ້ງ</h4>
                                        <p class="text-sm text-gray-600">ອອກຈາກລະບົບທຸກຄັ້ງເມື່ອໃຊ້ງານສຳເລັດ</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="bg-primary-100 p-2 rounded-full mr-3">
                                        <i class="fas fa-sync-alt text-primary-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">ປ່ຽນລະຫັດຜ່ານເປັນປະຈຳ</h4>
                                        <p class="text-sm text-gray-600">ປ່ຽນລະຫັດຜ່ານທຸກໆ 3-6 ເດືອນ</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- ການຈັດການຜູ້ໃຊ້ງານ -->
                    <section id="users" class="manual-section">
                        <h2 class="text-2xl font-bold text-primary-800 mb-6 header-underline">
                            <i class="fas fa-users mr-2 text-primary-600"></i> ການຈັດການຜູ້ໃຊ້ງານ
                        </h2>
                        
                        <div class="bg-primary-50 p-4 rounded-lg mb-6">
                            <div class="flex items-center mb-3">
                                <div class="bg-primary-100 p-2 rounded-full">
                                    <i class="fas fa-user-shield text-primary-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-primary-700 ml-3">ສຳລັບຜູ້ດູແລລະບົບ</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="card">
                                    <h4 class="font-medium text-primary-700 mb-3">ເພີ່ມຜູ້ໃຊ້ໃໝ່</h4>
                                    <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-600">
                                        <li>ໄປທີ່ "ຈັດການຜູ້ໃຊ້ງານ" > "ເພີ່ມຜູ້ໃຊ້ໃໝ່"</li>
                                        <li>ປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ (ຊື່, ອີເມວ, ເບີໂທ, ສິດການໃຊ້ງານ)</li>
                                        <li>ເລືອກວັດທີ່ກ່ຽວຂ້ອງ (ຖ້າມີ)</li>
                                        <li>ກົດ "ບັນທຶກ"</li>
                                    </ol>
                                </div>
                                
                                <div class="card">
                                    <h4 class="font-medium text-primary-700 mb-3">ແກ້ໄຂຜູ້ໃຊ້</h4>
                                    <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-600">
                                        <li>ຄົ້ນຫາຜູ້ໃຊ້ໃນຕາຕະລາງ</li>
                                        <li>ຄລິກໄອຄອນແກ້ໄຂ <i class="fas fa-edit text-blue-500"></i></li>
                                        <li>ອັບເດດຂໍ້ມູນທີ່ຕ້ອງການ</li>
                                        <li>ກົດ "ບັນທຶກການປ່ຽນແປງ"</li>
                                    </ol>
                                </div>
                                
                                <div class="card">
                                    <h4 class="font-medium text-primary-700 mb-3">ລຶບຜູ້ໃຊ້</h4>
                                    <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-600">
                                        <li>ຄົ້ນຫາຜູ້ໃຊ້ໃນຕາຕະລາງ</li>
                                        <li>ຄລິກໄອຄອນຖັງຂີ້ເຫຍື້ອ <i class="fas fa-trash text-red-500"></i></li>
                                        <li>ຢືນຢັນການລຶບໃນກ່ອງຂໍ້ຄວາມ</li>
                                    </ol>
                                    <div class="mt-3 bg-red-50 border-l-4 border-red-400 p-3 text-xs text-red-700">
                                        <p><i class="fas fa-exclamation-triangle"></i> <strong>ຄຳເຕືອນ:</strong> ການລຶບຜູ້ໃຊ້ບໍ່ສາມາດຍົກເລີກໄດ້!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="flex items-center mb-3">
                                <div class="bg-blue-100 p-2 rounded-full">
                                    <i class="fas fa-user text-blue-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-blue-700 ml-3">ສຳລັບຜູ້ໃຊ້ທົ່ວໄປ</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="card">
                                    <h4 class="font-medium text-blue-700 mb-3">ແກ້ໄຂໂປຣໄຟລ໌</h4>
                                    <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-600">
                                        <li>ຄລິກຊື່ຜູ້ໃຊ້ຂອງທ່ານທີ່ມຸມເທິງຂວາ</li>
                                        <li>ເລືອກ "ໂປຣໄຟລ໌"</li>
                                        <li>ອັບເດດຂໍ້ມູນສ່ວນຕົວຂອງທ່ານ</li>
                                        <li>ກົດ "ບັນທຶກ" ເພື່ອບັນທຶກການປ່ຽນແປງ</li>
                                    </ol>
                                </div>
                                
                                <div class="card">
                                    <h4 class="font-medium text-blue-700 mb-3">ປ່ຽນລະຫັດຜ່ານ</h4>
                                    <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-600">
                                        <li>ໄປທີ່ "ໂປຣໄຟລ໌" > "ປ່ຽນລະຫັດຜ່ານ"</li>
                                        <li>ປ້ອນລະຫັດຜ່ານປັດຈຸບັນຂອງທ່ານ</li>
                                        <li>ປ້ອນລະຫັດຜ່ານໃໝ່ແລະຢືນຢັນລະຫັດຜ່ານໃໝ່</li>
                                        <li>ກົດ "ບັນທຶກລະຫັດຜ່ານໃໝ່"</li>
                                    </ol>
                                    <div class="mt-3 bg-blue-50 border-l-4 border-blue-400 p-3 text-xs text-blue-700">
                                        <p><i class="fas fa-info-circle"></i> ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 8 ຕົວອັກສອນແລະປະກອບດ້ວຍຕົວອັກສອນພິມໃຫຍ່, ຕົວອັກສອນພິມນ້ອຍ, ແລະຕົວເລກ.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- ການຈັດການຂໍ້ມູນວັດ -->
                    <section id="temples" class="manual-section">
                        <h2 class="text-2xl font-bold text-primary-800 mb-6 header-underline">
                            <i class="fas fa-gopuram mr-2 text-primary-600"></i> ການຈັດການຂໍ້ມູນວັດ
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="card">
                                <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                    <i class="fas fa-plus-circle mr-2 text-green-500"></i> ເພີ່ມວັດໃໝ່
                                </h3>
                                <ol class="list-decimal pl-5 space-y-2 text-gray-600">
                                    <li>ໄປທີ່ "ຂໍ້ມູນວັດ" > "ເພີ່ມວັດໃໝ່"</li>
                                    <li>ປ້ອນຂໍ້ມູນວັດ (ຊື່, ສະຖານທີ່, ລາຍລະອຽດ)</li>
                                    <li>ປ້ອນທີ່ຢູ່ວັດໃຫ້ຄົບຖ້ວນ</li>
                                    <li>ອັບໂຫລດຮູບພາບວັດ (ສາມາດເພີ່ມໄດ້ຫຼາຍຮູບ)</li>
                                    <li>ເລືອກຜູ້ດູແລຫຼັກສຳລັບວັດ</li>
                                    <li>ກົດ "ບັນທຶກ"</li>
                                </ol>
                            </div>
                            
                            <div class="card">
                                <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                    <i class="fas fa-edit mr-2 text-blue-500"></i> ແກ້ໄຂຂໍ້ມູນວັດ
                                </h3>
                                <ol class="list-decimal pl-5 space-y-2 text-gray-600">
                                    <li>ໄປທີ່ "ຂໍ້ມູນວັດ" ແລະຄົ້ນຫາວັດທີ່ຕ້ອງການ</li>
                                    <li>ຄລິກໄອຄອນແກ້ໄຂຂໍ້ມູນ</li>
                                    <li>ອັບເດດຂໍ້ມູນທີ່ຕ້ອງການ</li>
                                    <li>ສາມາດເພີ່ມຫຼືລຶບຮູບພາບໄດ້</li>
                                    <li>ກົດ "ບັນທຶກການປ່ຽນແປງ"</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                <i class="fas fa-tasks mr-2 text-purple-500"></i> ຈັດການຂໍ້ມູນພະ
                            </h3>
                            
                            <div class="card">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h4 class="font-medium text-gray-700 mb-2">ເພີ່ມຂໍ້ມູນພະ</h4>
                                        <ol class="list-decimal pl-5 space-y-2 text-gray-600 text-sm">
                                            <li>ເລືອກວັດຈາກລາຍການ</li>
                                            <li>ໄປທີ່ "ຂໍ້ມູນພະ" > "ເພີ່ມຂໍ້ມູນພະ"</li>
                                            <li>ປ້ອນຂໍ້ມູນພະ (ຊື່, ປະຫວັດ, ປີສ້າງ)</li>
                                            <li>ອັບໂຫລດຮູບພາບພະ (ຖ້າມີ)</li>
                                            <li>ກົດ "ບັນທຶກຂໍ້ມູນພະ"</li>
                                        </ol>
                                    </div>
                                    
                                    <div>
                                        <h4 class="font-medium text-gray-700 mb-2">ແກ້ໄຂ/ລຶບຂໍ້ມູນພະ</h4>
                                        <ol class="list-decimal pl-5 space-y-2 text-gray-600 text-sm">
                                            <li>ເລືອກວັດຈາກລາຍການ</li>
                                            <li>ໄປທີ່ "ຂໍ້ມູນພະ"</li>
                                            <li>ຄລິກໄອຄອນແກ້ໄຂຂ້າງພະທີ່ຕ້ອງການ</li>
                                            <li>ອັບເດດຂໍ້ມູນທີ່ຕ້ອງການ</li>
                                            <li>ກົດ "ບັນທຶກການປ່ຽນແປງ" ຫຼື "ລຶບຂໍ້ມູນ"</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                <i class="fas fa-file-alt mr-2 text-yellow-600"></i> ການຈັດການເອກະສານ
                            </h3>
                            
                            <div class="card">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h4 class="font-medium text-gray-700 mb-2">ອັບໂຫລດເອກະສານ</h4>
                                        <ol class="list-decimal pl-5 space-y-2 text-gray-600 text-sm">
                                            <li>ເລືອກວັດ > "ເອກະສານ" > "ອັບໂຫລດເອກະສານໃໝ່"</li>
                                            <li>ເລືອກປະເພດເອກະສານ</li>
                                            <li>ປ້ອນຊື່ແລະຄຳອະທິບາຍ</li>
                                            <li>ເລືອກໄຟລ໌ທີ່ຕ້ອງການອັບໂຫລດ</li>
                                            <li>ກົດ "ອັບໂຫລດ"</li>
                                        </ol>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-info-circle"></i> ຮອງຮັບໄຟລ໌: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (ຂະໜາດສູງສຸດ: 10MB)
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <h4 class="font-medium text-gray-700 mb-2">ຈັດການເອກະສານ</h4>
                                        <ol class="list-decimal pl-5 space-y-2 text-gray-600 text-sm">
                                            <li>ໄປທີ່ "ເອກະສານ"</li>
                                            <li>ຄົ້ນຫາເອກະສານໂດຍໃຊ້ຕົວກອງ</li>
                                            <li>ສາມາດດາວໂຫລດ, ແກ້ໄຂລາຍລະອຽດ, ຫຼືລຶບເອກະສານ</li>
                                            <li>ໃຊ້ໄອຄອນເພື່ອດາວໂຫລດ <i class="fas fa-download text-blue-500"></i> ຫຼື ລຶບ <i class="fas fa-trash text-red-500"></i></li>
                                        </ol>
                                        <div class="mt-3 bg-yellow-50 border-l-4 border-yellow-400 p-3 text-xs text-yellow-700">
                                            <p><i class="fas fa-exclamation-triangle"></i> <strong>ໝາຍເຫດ:</strong> ການລຶບເອກະສານບໍ່ສາມາດຍົກເລີກໄດ້.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- ຈັດການກິດຈະກຳ -->
                    <section id="events" class="manual-section">
                        <h2 class="text-2xl font-bold text-primary-800 mb-6 header-underline">
                            <i class="fas fa-calendar-alt mr-2 text-primary-600"></i> ຈັດການກິດຈະກຳ ແລະ ການນັດໝາຍ
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="card">
                                <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                    <i class="fas fa-calendar-plus mr-2 text-green-500"></i> ເພີ່ມກິດຈະກຳໃໝ່
                                </h3>
                                <ol class="list-decimal pl-5 space-y-2 text-gray-600">
                                    <li>ໄປທີ່ "ກິດຈະກຳ" > "ເພີ່ມກິດຈະກຳໃໝ່"</li>
                                    <li>ປ້ອນຊື່ກິດຈະກຳ, ລາຍລະອຽດ</li>
                                    <li>ເລືອກວັນທີ ແລະ ເວລາເລີ່ມຕົ້ນ/ສິ້ນສຸດ</li>
                                    <li>ເລືອກວັດທີ່ຈັດກິດຈະກຳ</li>
                                    <li>ເລືອກປະເພດກິດຈະກຳ (ເຊັ່ນ: ບຸນປະເພນີ, ງານບຸນ, ກິດຈະກຳເຜີຍແຜ່)</li>
                                    <li>ກຳນົດຜູເຂົ້າຮ່ວມ (ຖ້າຈຳເປັນ)</li>
                                    <li>ອັບໂຫລດຮູບພາບທີ່ກ່ຽວຂ້ອງ (ຖ້າມີ)</li>
                                    <li>ຕັ້ງຄ່າການແຈ້ງເຕືອນ (ຖ້າຕ້ອງການ)</li>
                                    <li>ກົດ "ບັນທຶກ"</li>
                                </ol>
                            </div>
                            
                            <div class="card">
                                <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                    <i class="fas fa-calendar-check mr-2 text-blue-500"></i> ເບິ່ງປະຕິທິນ
                                </h3>
                                <p class="text-gray-600 mb-4">ປະຕິທິນຊ່ວຍໃຫ້ທ່ານສາມາດເບິ່ງພາບລວມຂອງກິດຈະກຳທັງໝົດໃນຫຼາຍຮູບແບບ.</p>
                                
                                <h4 class="font-medium text-gray-700 mb-2">ມຸມມອງຕ່າງໆ:</h4>
                                <ul class="list-disc pl-5 space-y-2 text-gray-600">
                                    <li>
                                        <strong>ມຸມມອງເດືອນ</strong> - ເບິ່ງກິດຈະກຳທັງໝົດໃນເດືອນ
                                        <div class="mt-1 text-xs text-gray-500">
                                            <i class="far fa-calendar mr-1"></i> ເໝາະສຳລັບການວາງແຜນລ່ວງໜ້າ
                                        </div>
                                    </li>
                                    <li>
                                        <strong>ມຸມມອງອາທິດ</strong> - ເບິ່ງກິດຈະກຳຕາມອາທິດ
                                        <div class="mt-1 text-xs text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i> ເໝາະສຳລັບການວາງແຜນອາທິດ
                                        </div>
                                    </li>
                                    <li>
                                        <strong>ມຸມມອງວັນ</strong> - ເບິ່ງກິດຈະກຳທັງໝົດໃນມື້ດຽວ
                                        <div class="mt-1 text-xs text-gray-500">
                                            <i class="far fa-clock mr-1"></i> ເໝາະສຳລັບການເບິ່ງຕາຕະລາງປະຈຳວັນ
                                        </div>
                                    </li>
                                </ul>
                                
                                <div class="mt-4">
                                    <h4 class="font-medium text-gray-700 mb-2">ຄຸນສົມບັດເພີ່ມເຕີມ:</h4>
                                    <ul class="space-y-1 text-gray-600 text-sm">
                                        <li><i class="fas fa-filter text-primary-500 mr-1"></i> ກອງໂດຍປະເພດ ຫຼື ວັດ</li>
                                        <li><i class="fas fa-search text-primary-500 mr-1"></i> ຄົ້ນຫາກິດຈະກຳ</li>
                                        <li><i class="fas fa-share-alt text-primary-500 mr-1"></i> ແຊຣ໌ກັບຜູ້ໃຊ້ອື່ນໄດ້</li>
                                        <li><i class="fas fa-print text-primary-500 mr-1"></i> ພິມປະຕິທິນອອກມາໃຊ້ງານ</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                <i class="fas fa-bell mr-2 text-purple-500"></i> ການແຈ້ງເຕືອນກິດຈະກຳ
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-2">ຕັ້ງຄ່າການແຈ້ງເຕືອນ</h4>
                                    <p class="text-gray-600 text-sm mb-3">ຕັ້ງຄ່າການແຈ້ງເຕືອນເພື່ອໃຫ້ຜູເຂົ້າຮ່ວມໄດ້ຮັບການເຕືອນກ່ອນກິດຈະກຳ.</p>
                                    
                                    <ol class="list-decimal pl-5 space-y-2 text-gray-600 text-sm">
                                        <li>ໄປທີ່ກິດຈະກຳທີ່ຕ້ອງການ > "ຕັ້ງຄ່າການແຈ້ງເຕືອນ"</li>
                                        <li>ເລືອກຮູບແບບການແຈ້ງເຕືອນ (Email, SMS, ຫຼື ທັງສອງ)</li>
                                        <li>ກຳນົດເວລາແຈ້ງເຕືອນ (1 ວັນ, 1 ຊົ່ວໂມງ, 30 ນາທີ ກ່ອນກິດຈະກຳ)</li>
                                        <li>ເລືອກຜູ້ຮັບການແຈ້ງເຕືອນ</li>
                                        <li>ກົດ "ບັນທຶກການຕັ້ງຄ່າ"</li>
                                    </ol>
                                </div>
                                
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-2">ຮູບແບບການແຈ້ງເຕືອນ</h4>
                                    <div class="space-y-3 text-sm">
                                        <div class="flex items-start">
                                            <div class="bg-blue-100 p-1 rounded-full mr-2">
                                                <i class="fas fa-envelope text-blue-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-medium text-gray-700">ແຈ້ງເຕືອນທາງ Email</h5>
                                                <p class="text-xs text-gray-600">ສົ່ງອີເມວແຈ້ງເຕືອນພ້ອມລາຍລະອຽດກິດຈະກຳ</p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-start">
                                            <div class="bg-green-100 p-1 rounded-full mr-2">
                                                <i class="fas fa-sms text-green-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-medium text-gray-700">ແຈ້ງເຕືອນທາງ SMS</h5>
                                                <p class="text-xs text-gray-600">ສົ່ງຂໍ້ຄວາມເຕືອນໄປຍັງເບີໂທລະສັບ</p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-start">
                                            <div class="bg-amber-100 p-1 rounded-full mr-2">
                                                <i class="fas fa-bell text-amber-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-medium text-gray-700">ແຈ້ງເຕືອນໃນລະບົບ</h5>
                                                <p class="text-xs text-gray-600">ສະແດງການແຈ້ງເຕືອນເມື່ອຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບ</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 bg-blue-50 border-l-4 border-blue-400 p-3 text-sm text-blue-700">
                                <p><i class="fas fa-info-circle"></i> <strong>ເຄັດລັບ:</strong> ທ່ານສາມາດສົ່ງອອກຂໍ້ມູນກິດຈະກຳເຂົ້າ Google Calendar ຫຼື Apple Calendar ໄດ້ໂດຍຄລິກປຸ່ມ "ສົ່ງອອກ" ຢູ່ໃນລາຍລະອຽດກິດຈະກຳ.</p>
                            </div>
                        </div>
                    </section>
                    
                    <!-- ຈັດການການບໍລິຈາກ -->
                    <section id="donations" class="manual-section">
                        <h2 class="text-2xl font-bold text-primary-800 mb-6 header-underline">
                            <i class="fas fa-hand-holding-heart mr-2 text-primary-600"></i> ຈັດການການບໍລິຈາກ ແລະ ການເງິນ
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="card">
                                <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                    <i class="fas fa-donate mr-2 text-green-500"></i> ບັນທຶກການບໍລິຈາກ
                                </h3>
                                <ol class="list-decimal pl-5 space-y-2 text-gray-600">
                                    <li>ໄປທີ່ "ການເງິນ" > "ເພີ່ມການບໍລິຈາກໃໝ່"</li>
                                    <li>ປ້ອນຂໍ້ມູນຜູ້ບໍລິຈາກ (ຊື່, ເບີໂທ, ອີເມວ)</li>
                                    <li>ເລືອກວັດທີ່ຕ້ອງການບໍລິຈາກ</li>
                                    <li>ປ້ອນຈຳນວນເງິນ ແລະ ສະກຸນເງິນ</li>
                                    <li>ລະບຸຈຸດປະສົງຂອງການບໍລິຈາກ</li>
                                    <li>ເລືອກວິທີການຊຳລະເງິນ (ເງິນສົດ, ໂອນ, ອື່ນໆ)</li>
                                    <li>ອັບໂຫລດຫຼັກຖານການໂອນເງິນ (ຖ້າມີ)</li>
                                    <li>ກົດ "ບັນທຶກ"</li>
                                </ol>
                            </div>
                            
                            <div class="card">
                                <h3 class="flex items-center text-lg font-semibold text-primary-700 mb-3">
                                    <i class="fas fa-chart-bar mr-2 text-purple-500"></i> ເບິ່ງລາຍງານການເງິນ
                                </h3>
                                <ol class="list-decimal pl-5 space-y-2 text-gray-600">
                                    <li>ໄປທີ່ "ລາຍງານ" > "ການເງິນ"</li>
                                    <li>ເລືອກປະເພດລາຍງານທີ່ຕ້ອງການ:
                                        <ul class="list-disc pl-5 mt-1 text-sm">
                                            <li>ລາຍງານການບໍລິຈາກ</li>
                                            <li>ລາຍງານລາຍຮັບ</li>
                                            <li>ລາຍງານລາຍຈ່າຍ</li>
                                            <li>ລາຍງານສະຫຼຸບ</li>
                                        </ul>
                                    </li>
                                    <li>ເລືອກຊ່ວງວັນທີທີ່ຕ້ອງການ</li>
                                    <li>ເລືອກວັດ (ຫຼືທັງໝົດ ຖ້າມີສິດ)</li>
                                    <li>ກົດ "ສ້າງລາຍງານ"</li>
                                    <li>ສາມາດສົ່ງອອກເປັນ PDF, Excel, ຫຼື ພິມລາຍງານ</li>
                                </ol>
                                
                                <div class="mt-4 flex items-start bg-yellow-50 p-3 rounded-lg">
                                    <div class="flex-shrink-0 text-yellow-500">
                                        <i class="fas fa-lightbulb"></i>
                                    </div>
                                    <div class="ml-3 text-xs text-yellow-700">
                                        <p>ທ່ານສາມາດສ້າງລາຍງານປະຈຳເດືອນແບບອັດຕະໂນມັດ ໂດຍໄປທີ່ "ລາຍງານ" > "ຕັ້ງຄ່າການລາຍງານ" > "ລາຍງານປະຈຳເດືອນ" ແລະ ກຳນົດການຕັ້ງຄ່າໃຫ້ສົ່ງລາຍງານຫາອີເມວຂອງທ່ານທຸກໆ ສິ້ນເດືອນ.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- ການຕິດຕໍ່ພວກເຮົາ -->
                    <section id="contact" class="manual-section">
                        <h2 class="text-2xl font-bold text-primary-800 mb-6 header-underline">
                            <i class="fas fa-phone-alt mr-2 text-primary-600"></i> ຕິດຕໍ່ພວກເຮົາ
                        </h2>
                        
                        <div class="card">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-lg font-semibold text-primary-700 mb-4">ຂໍຄວາມຊ່ວຍເຫຼືອ</h3>
                                    <p class="text-gray-600 mb-4">ຖ້າທ່ານຕ້ອງການຄວາມຊ່ວຍເຫຼືອເພີ່ມເຕີມ, ກະລຸນາຕິດຕໍ່ພວກເຮົາຜ່ານຊ່ອງທາງດັ່ງນີ້:</p>
                                    
                                    <ul class="space-y-3">
                                        <li class="flex items-center">
                                            <div class="bg-primary-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-envelope text-primary-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium">ອີເມວ</h4>
                                                <p class="text-primary-600">phathasyla@gmail.com</p>
                                            </div>
                                        </li>
                                        <li class="flex items-center">
                                            <div class="bg-primary-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-phone-alt text-primary-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium">ໂທລະສັບ</h4>
                                                <p class="text-primary-600">+856 20 77772338</p>
                                            </div>
                                        </li>
                                        <li class="flex items-center">
                                            <div class="bg-primary-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-clock text-primary-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium">ເວລາເຮັດວຽກ</h4>
                                                <p>ວັນຈັນ-ວັນສຸກ, 8:00 - 17:00</p>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                
                                <div>
                                    <h3 class="text-lg font-semibold text-primary-700 mb-4">ແຫຼ່ງຂໍ້ມູນເພີ່ມເຕີມ</h3>
                                    <ul class="space-y-3">
                                        <li class="flex items-center">
                                            <div class="bg-blue-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-book text-blue-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium">ເອກະສານອອນໄລນ໌</h4>
                                                <a href="#" class="text-blue-600 hover:underline">docs.yourdomain.com</a>
                                            </div>
                                        </li>
                                        <li class="flex items-center">
                                            <div class="bg-green-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-video text-green-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium">ວິດີໂອສອນນຳໃຊ້</h4>
                                                <a href="#" class="text-green-600 hover:underline">yourdomain.com/tutorials</a>
                                            </div>
                                        </li>
                                        <li class="flex items-center">
                                            <div class="bg-purple-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-comments text-purple-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium">ຊຸມຊົນຊ່ວຍເຫຼືອ</h4>
                                                <a href="#" class="text-purple-600 hover:underline">community.yourdomain.com</a>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-gray-800 py-8 text-white">
        <div class="section-container">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-center md:text-left">
                        &copy; 2025 ລະບົບຈັດການວັດ. ສະຫງວນລິຂະສິດທັງໝົດ.
                    </p>
                    <p class="text-sm text-gray-400 text-center md:text-left">
                        ເວີຊັ່ນຄູ່ມື: 1.0
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="hover:text-primary-300 transition-colors">
                        <i class="fab fa-facebook-square text-xl"></i>
                    </a>
                    <a href="#" class="hover:text-primary-300 transition-colors">
                        <i class="fab fa-youtube text-xl"></i>
                    </a>
                    <a href="#" class="hover:text-primary-300 transition-colors">
                        <i class="fab fa-line text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>
    
    <button id="topButton" class="top-scroll-btn" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <script src="assets/js/about-script.js"></script>
</body>
</html>