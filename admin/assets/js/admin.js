// admin.js
document.addEventListener('DOMContentLoaded', function() {
    // User search functionality
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
        userSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.user-table tbody tr');
            
            rows.forEach(row => {
                const username = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                
                if (username.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Edit user modal
    const editButtons = document.querySelectorAll('.edit-btn');
    const editModal = document.getElementById('editUserModal');
    const closeEditModal = document.getElementById('closeEditModal');
    const editUserForm = document.getElementById('editUserForm');
    
    // Open edit modal
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.id;
            const row = this.closest('tr');
            
            // Fill form with user data
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = row.cells[1].textContent;
            document.getElementById('edit_email').value = row.cells[2].textContent;
            
            // Set status value
            const isVerified = row.cells[3].textContent.trim() === 'Verified';
            document.getElementById('edit_status').value = isVerified ? '1' : '0';
            
         
            editModal.classList.remove('hidden');
        });
    });
    
    // Close edit modal
    if (closeEditModal) {
        closeEditModal.addEventListener('click', function() {
            editModal.classList.add('hidden');
        });
    }
    
    
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                editModal.classList.add('hidden');
            }
        });
    }
    
    // Submit edit form with AJAX
    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('edit_user_id').value;
            const username = document.getElementById('edit_username').value;
            const email = document.getElementById('edit_email').value;
            const status = document.getElementById('edit_status').value;
            
            // Create form data for AJAX
            const formData = new FormData();
            formData.append('action', 'edit');
            formData.append('user_id', userId);
            formData.append('username', username);
            formData.append('email', email);
            formData.append('status', status);
            
            // Send AJAX request
            fetch('user_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    
                    editModal.classList.add('hidden');
                    window.location.reload();
                } else {
                    alert('Error updating user: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the user.');
            });
        });
    }
    
    
});

// logout 
document.addEventListener('DOMContentLoaded', function() {
   
    const logoutLink = document.querySelector('a[href="logout.php"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            
            
            document.getElementById('logoutModal').classList.remove('hidden');
        });
    }
    
  
    const cancelLogout = document.getElementById('cancelLogout');
    if (cancelLogout) {
        cancelLogout.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('logoutModal').classList.add('hidden');
        });
    }
    

    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                logoutModal.classList.add('hidden');
            }
        });
    }
});




function showDeleteModal(userId) {

    document.getElementById('delete_user_id').value = userId;
    

    var modal = document.getElementById('deleteModal');
    modal.classList.remove('hidden');
    modal.classList.add('show');
}

document.addEventListener('DOMContentLoaded', function() {

    var deleteModal = document.getElementById('deleteModal');
    var closeButton = document.getElementById('closeDeleteModal');
    var cancelButton = document.getElementById('cancelDelete');
    

    closeButton.onclick = function() {
        deleteModal.classList.remove('show');
        deleteModal.classList.add('hidden');
    }
    

    cancelButton.onclick = function() {
        deleteModal.classList.remove('show');
        deleteModal.classList.add('hidden');
    }
    

    window.onclick = function(event) {
        if (event.target == deleteModal) {
            deleteModal.classList.remove('show');
            deleteModal.classList.add('hidden');
        }
    }
});