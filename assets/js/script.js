// DOM elements
const addQuestBtn = document.getElementById('addQuestBtn');
const addQuestModal = document.getElementById('addQuestModal');
const closeModalBtn = document.getElementById('closeModal');
const addQuestForm = document.getElementById('addQuestForm');
const timerDisplay = document.getElementById('timer');
const currentDateDisplay = document.getElementById('currentDate');
const difficultyButtons = document.querySelectorAll('.difficulty-btn');
const deleteButtons = document.querySelectorAll('.delete-quest');
const completeButtons = document.querySelectorAll('.complete-quest');
const startButtons = document.querySelectorAll('.start-quest');

// Current selected difficulty
let selectedDifficulty = 'Easy';

// Update timer display
function updateTimer() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    timerDisplay.textContent = `${hours}:${minutes}:${seconds}`;
}

// Update date display
function updateDate() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const year = String(now.getFullYear()).slice(-2);
    currentDateDisplay.textContent = `${month}/${day}/${year}`;
}

// Initialize clock and date
function initClock() {
    updateTimer();
    updateDate();
    setInterval(updateTimer, 1000);
    // Update date once per day is enough, but for demo purposes:
    setInterval(updateDate, 60000);
}

// Modal controls
function openModal() {
    addQuestModal.classList.remove('hidden');
}

function closeModal() {
    addQuestModal.classList.add('hidden');
}

// Form Submission - uses standard form submit to reload page with PHP processing
addQuestForm.addEventListener('submit', (e) => {
    // Form submission will be handled by PHP
    // This is just for any client-side validation or pre-processing
    const questName = document.getElementById('quest_name').value.trim();
    const difficulty = document.getElementById('difficulty').value;
    const time = document.getElementById('time').value.trim();
    
    if (!questName || !time) {
        e.preventDefault();
        alert('Please fill out all fields');
    }
});

// Handle difficulty selection
difficultyButtons.forEach(button => {
    button.addEventListener('click', function() {
        // Update visual selection
        difficultyButtons.forEach(btn => {
            btn.style.opacity = '1';
        });
        this.style.opacity = '0.7';
        
        selectedDifficulty = this.dataset.difficulty;
        
        // Update form difficulty dropdown if modal is open
        const difficultySelect = document.getElementById('difficulty');
        if (difficultySelect) {
            difficultySelect.value = selectedDifficulty;
        }
    });
});

// Add click event listeners to action buttons
function setupActionButtons() {
    // Delete quest buttons
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this quest?')) {
                const questId = this.dataset.id;
                
                // Create and submit form for delete action
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_quest';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'quest_id';
                idInput.value = questId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Complete quest buttons
    completeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const questId = this.dataset.id;
            
            // Create and submit form for complete action
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'complete_quest';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'quest_id';
            idInput.value = questId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        });
    });
    
    // Start quest buttons
    startButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const questId = this.dataset.id;
            
            // Create and submit form for start action
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'start_quest';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'quest_id';
            idInput.value = questId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        });
    });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    initClock();
    setupActionButtons();
    
    // Modal control
    addQuestBtn.addEventListener('click', openModal);
    closeModalBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    addQuestModal.addEventListener('click', function(e) {
        if (e.target === addQuestModal) {
            closeModal();
        }
    });
});

// Adjust UI based on viewport size
function adjustUIForViewport() {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Adjust quest containers based on screen size
    if (viewportWidth <= 768) {
        // For smaller screens
        document.querySelectorAll('.quest-container').forEach(container => {
            container.style.marginBottom = '20px';
        });
        
        // On mobile, limit quest items container height based on screen size
        const questItemsContainers = document.querySelectorAll('.quest-items-container');
        questItemsContainers.forEach(container => {
            // Set a reasonable max height based on viewport
            const maxHeight = Math.min(viewportHeight * 0.3, 300);
            container.style.maxHeight = `${maxHeight}px`;
        });
        
        // Apply touch-friendly styles for mobile
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.style.minWidth = '28px';
            btn.style.minHeight = '28px';
        });
        
    } else if (viewportWidth <= 992) {
        // For tablets
        document.querySelectorAll('.quest-container').forEach(container => {
            container.style.marginBottom = '20px';
        });
        
        document.querySelectorAll('.quest-items-container').forEach(container => {
            container.style.maxHeight = '50vh';
        });
        
        // Reset touch-friendly styles
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.style.minWidth = '';
            btn.style.minHeight = '';
        });
        
    } else {
        // For desktop
        document.querySelectorAll('.quest-container').forEach(container => {
            container.style.marginBottom = '0';
        });
        
        document.querySelectorAll('.quest-items-container').forEach(container => {
            container.style.maxHeight = '60vh';
        });
        
        // Reset touch-friendly styles
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.style.minWidth = '';
            btn.style.minHeight = '';
        });
    }
    
    // Handle extremely small screens (like old iPhones or small mobile devices)
    if (viewportWidth <= 320) {
        document.querySelectorAll('.category-header').forEach(header => {
            header.style.fontSize = '12px';
            header.style.padding = '3px 8px';
        });
    }
}

// Run on load, on resize, and on orientation change
window.addEventListener('load', adjustUIForViewport);
window.addEventListener('resize', adjustUIForViewport);
window.addEventListener('orientationchange', adjustUIForViewport);

// Touch event optimizations for mobile
function setupTouchEvents() {
    // Detect if we're on a touch device
    const isTouchDevice = ('ontouchstart' in window) || 
                           (navigator.maxTouchPoints > 0) ||
                           (navigator.msMaxTouchPoints > 0);
    
    if (isTouchDevice) {
        // Add active states for buttons via touch events
        const allButtons = document.querySelectorAll('.menu-button, .category-option, .action-btn, .submit-btn');
        
        allButtons.forEach(button => {
            // Add touch feedback
            button.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            }, { passive: true });
            
            button.addEventListener('touchend', function() {
                this.classList.remove('touch-active');
            }, { passive: true });
            
            button.addEventListener('touchcancel', function() {
                this.classList.remove('touch-active');
            }, { passive: true });
        });
        
        // Improve scrolling on quest containers
        const questContainers = document.querySelectorAll('.quest-items-container');
        questContainers.forEach(container => {
            container.style.webkitOverflowScrolling = 'touch'; // Smooth scrolling on iOS
        });
        
        // Prevent zooming when double-tapping buttons on iOS
        const allInteractiveElements = document.querySelectorAll('button, input, select');
        allInteractiveElements.forEach(element => {
            element.addEventListener('touchend', function(e) {
                e.preventDefault();
                // Still trigger the click after preventing default
                this.click();
            }, { passive: false });
        });
    }
}

// Call the setup function when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupTouchEvents();
});
