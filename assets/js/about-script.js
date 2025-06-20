// ฟังก์ชันสำหรับ Toggle FAQ
function toggleFaq(element) {
    const answer = element.nextElementSibling;
    const icon = element.querySelector('i');
    
    if (answer.classList.contains('show')) {
        answer.classList.remove('show');
        icon.style.transform = "rotate(0deg)";
    } else {
        // ปิดทุก FAQ ก่อน
        document.querySelectorAll('.faq-answer').forEach(item => {
            item.classList.remove('show');
        });
        
        document.querySelectorAll('.faq-question i').forEach(item => {
            item.style.transform = "rotate(0deg)";
        });
        
        // เปิด FAQ ที่เลือก
        answer.classList.add('show');
        icon.style.transform = "rotate(180deg)";
    }
}

// ปุ่มเลื่อนขึ้นด้านบน
window.onscroll = function() {
    const topButton = document.getElementById('topButton');
    if (document.body.scrollTop > 500 || document.documentElement.scrollTop > 500) {
        topButton.classList.add('visible');
    } else {
        topButton.classList.remove('visible');
    }
};

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// เพิ่มการเน้นรายการเมนูที่กำลังอยู่
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.manual-section');
    const menuItems = document.querySelectorAll('.toc a');
    
    window.addEventListener('scroll', function() {
        let current = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            
            if (pageYOffset >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });
        
        menuItems.forEach(item => {
            item.parentElement.classList.remove('active-menu');
            const href = item.getAttribute('href').substring(1);
            
            if (href === current) {
                item.parentElement.classList.add('active-menu');
                item.classList.add('text-primary-600');
                item.classList.remove('text-gray-600');
            } else {
                item.classList.remove('text-primary-600');
                item.classList.add('text-gray-600');
            }
        });
    });
});