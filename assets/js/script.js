// JavaScript for interactive functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for buttons
    const addQuestBtn = document.querySelector('.difficulty-btn');
    if (addQuestBtn) {
        addQuestBtn.addEventListener('click', function() {
            alert('Add Quest button clicked! You can implement a modal or form here.');
        });
    }
    
    // Add event listeners for checkbox circles to toggle completion state
    const checkboxCircles = document.querySelectorAll('.checkbox-circle');
    checkboxCircles.forEach(circle => {
        circle.addEventListener('click', function() {
            this.classList.toggle('bg-green-500');
        });
    });
    
    // Add event listeners for the complete/cancel buttons
    const completeButtons = document.querySelectorAll('.bg-green-500');
    const cancelButtons = document.querySelectorAll('.bg-red-500');
    
    completeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const questItem = this.closest('.quest-item');
            if (questItem) {
                questItem.style.opacity = '0.5';
                setTimeout(() => {
                    questItem.remove();
                }, 500);
            }
        });
    });
    
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const questItem = this.closest('.quest-item');
            if (questItem) {
                questItem.remove();
            }
        });
    });
}); 