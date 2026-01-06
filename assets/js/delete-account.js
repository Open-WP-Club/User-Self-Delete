/**
 * User Self Delete - Frontend JavaScript
 */

(function($) {
  'use strict';

  var UserSelfDelete = {
      
      /**
       * Initialize
       */
      init: function() {
          this.bindEvents();
      },
      
      /**
       * Bind events
       */
      bindEvents: function() {
          // Delete account trigger
          $(document).on('click', '#delete-account-trigger', this.showModal);
          
          // Modal controls
          $(document).on('click', '.close-modal, #cancel-deletion', this.hideModal);
          $(document).on('click', '#confirm-deletion', this.confirmDeletion);
          
          // Password input validation
          $(document).on('input', '#confirm-password', this.validatePassword);
          
          // Close modal on outside click
          $(document).on('click', '#delete-account-modal', function(e) {
              if (e.target === this) {
                  UserSelfDelete.hideModal();
              }
          });
          
          // Prevent modal content click from closing modal
          $(document).on('click', '.modal-content', function(e) {
              e.stopPropagation();
          });
          
          // Handle escape key
          $(document).on('keydown', function(e) {
              if (e.keyCode === 27 && $('#delete-account-modal').is(':visible')) {
                  UserSelfDelete.hideModal();
              }
          });
      },
      
      /**
       * Show confirmation modal
       */
      showModal: function(e) {
          e.preventDefault();
          
          var modal = $('#delete-account-modal');
          if (modal.length === 0) {
              console.error('Delete account modal not found');
              return;
          }
          
          // Reset modal state
          $('#confirm-password').val('');
          $('#confirm-deletion').prop('disabled', true);
          $('.password-error').hide();
          
          // Show modal
          modal.fadeIn(300);
          
          // Focus on password field
          setTimeout(function() {
              $('#confirm-password').focus();
          }, 350);
          
          // Prevent body scroll
          $('body').addClass('modal-open');
      },
      
      /**
       * Hide confirmation modal
       */
      hideModal: function(e) {
          if (e) {
              e.preventDefault();
          }
          
          $('#delete-account-modal').fadeOut(300);
          $('body').removeClass('modal-open');
          
          // Clear any error messages
          $('.password-error').hide();
      },
      
      /**
       * Validate password input
       */
      validatePassword: function() {
          var password = $(this).val();
          var confirmBtn = $('#confirm-deletion');
          
          if (password.length > 0) {
              confirmBtn.prop('disabled', false);
          } else {
              confirmBtn.prop('disabled', true);
          }
          
          // Hide any previous error messages
          $('.password-error').hide();
      },
      
      /**
       * Confirm account deletion
       */
      confirmDeletion: function(e) {
          e.preventDefault();
          
          var password = $('#confirm-password').val();
          var $button = $(this);
          var $errorDiv = $('.password-error');
          
          // Validate password
          if (!password) {
              $errorDiv.text(userSelfDelete.passwordLabel).show();
              $('#confirm-password').focus();
              return;
          }
          
          // Show confirmation dialog
          if (!confirm(userSelfDelete.confirmText)) {
              return;
          }
          
          // Disable button and show processing state
          $button.prop('disabled', true).text(userSelfDelete.processing);
          
          // Make AJAX request
          $.ajax({
              url: userSelfDelete.ajaxUrl,
              type: 'POST',
              data: {
                  action: 'delete_user_account',
                  nonce: userSelfDelete.nonce,
                  password: password
              },
              success: function(response) {
                  if (response.success) {
                      // Show success message and redirect
                      UserSelfDelete.showSuccessMessage(response.data.message);
                      
                      // Redirect after a short delay
                      setTimeout(function() {
                          window.location.href = response.data.redirect || '/';
                      }, 2000);
                      
                  } else {
                      // Show error message
                      UserSelfDelete.showError(response.data || userSelfDelete.error);
                      $button.prop('disabled', false).text(userSelfDelete.deleteButton);
                  }
              },
              error: function(xhr, status, error) {
                  console.error('Deletion request failed:', error);
                  UserSelfDelete.showError(userSelfDelete.error);
                  $button.prop('disabled', false).text(userSelfDelete.deleteButton);
              }
          });
      },
      
      /**
       * Show error message
       */
      showError: function(message) {
          var $errorDiv = $('.password-error');
          $errorDiv.text(message).show();
          
          // Focus back to password field
          $('#confirm-password').focus().select();
      },
      
      /**
       * Show success message
       */
      showSuccessMessage: function(message) {
          // Hide the modal
          this.hideModal();
          
          // Create and show success notification
          var $notification = $('<div class="user-delete-success-notification">')
              .html('<div class="success-content"><strong>✓ ' + message + '</strong><br>You will be redirected shortly...</div>')
              .css({
                  position: 'fixed',
                  top: '20px',
                  right: '20px',
                  background: '#4CAF50',
                  color: 'white',
                  padding: '15px 20px',
                  borderRadius: '5px',
                  boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                  zIndex: 10000,
                  maxWidth: '400px',
                  fontSize: '14px',
                  lineHeight: '1.4'
              });
          
          $('body').append($notification);
          
          // Animate in
          $notification.hide().fadeIn(300);
      },
      
      /**
       * Utility: Log debug information
       */
      log: function(message, data) {
          if (console && console.log) {
              console.log('[User Self Delete] ' + message, data || '');
          }
      }
  };
  
  // Initialize when document is ready
  $(document).ready(function() {
      UserSelfDelete.init();
  });
  
  // Make available globally for debugging
  window.UserSelfDelete = UserSelfDelete;

})(jQuery);