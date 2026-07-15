/**
 * User Self Delete - Frontend JavaScript (Vanilla ES6+)
 *
 * @package UserSelfDelete
 * @since 2.0.0
 */

class UserSelfDelete {
	/**
	 * Initialize the delete account functionality.
	 */
	constructor() {
		this.modal = null;
		this.confirmButton = null;
		this.cancelButton = null;
		this.passwordInput = null;
		this.passwordError = null;
		this.deleteButton = null;
		this.config = window.userSelfDelete || {};

		this.init();
	}

	/**
	 * Initialize and bind events.
	 */
	init() {
		// Wait for DOM to be ready.
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', () => this.bindEvents() );
		} else {
			this.bindEvents();
		}
	}

	/**
	 * Bind all event listeners.
	 */
	bindEvents() {
		// Cache DOM elements.
		this.modal = document.getElementById( 'delete-account-modal' );
		this.deleteButton = document.getElementById( 'delete-account-trigger' );
		this.confirmButton = document.getElementById( 'confirm-deletion' );
		this.cancelButton = document.getElementById( 'cancel-deletion' );
		this.passwordInput = document.getElementById( 'confirm-password' );
		this.passwordError = this.modal?.querySelector( '.password-error' );

		if ( ! this.modal || ! this.deleteButton ) {
			return;
		}

		// Delete account trigger.
		this.deleteButton.addEventListener( 'click', ( e ) => this.showModal( e ) );

		// Modal controls.
		const closeButton = this.modal.querySelector( '.close-modal' );
		closeButton?.addEventListener( 'click', ( e ) => this.hideModal( e ) );
		this.cancelButton?.addEventListener( 'click', ( e ) => this.hideModal( e ) );
		this.confirmButton?.addEventListener( 'click', ( e ) => this.confirmDeletion( e ) );

		// Password input validation.
		this.passwordInput?.addEventListener( 'input', () => this.validatePassword() );

		// Allow Enter key to submit when password is valid.
		this.passwordInput?.addEventListener( 'keypress', ( e ) => {
			if ( e.key === 'Enter' && ! this.confirmButton.disabled ) {
				this.confirmDeletion( e );
			}
		} );

		// Close modal on outside click.
		this.modal.addEventListener( 'click', ( e ) => {
			if ( e.target === this.modal ) {
				this.hideModal();
			}
		} );

		// Prevent modal content click from closing modal.
		const modalContent = this.modal.querySelector( '.modal-content' );
		modalContent?.addEventListener( 'click', ( e ) => {
			e.stopPropagation();
		} );

		// Focus trap.
		this.modal.addEventListener( 'keydown', ( e ) => this.trapFocus( e ) );

		// Handle escape key.
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && this.isModalVisible() ) {
				this.hideModal();
			}
		} );
	}

	/**
	 * Check if modal is visible.
	 *
	 * @return {boolean} True if modal is visible.
	 */
	isModalVisible() {
		return this.modal && this.modal.style.display !== 'none';
	}

	/**
	 * Show confirmation modal.
	 *
	 * @param {Event} e Click event.
	 */
	showModal( e ) {
		e?.preventDefault();

		if ( ! this.modal ) {
			console.error( 'Delete account modal not found' );
			return;
		}

		// Reset modal state.
		if ( this.passwordInput ) {
			this.passwordInput.value = '';
		}

		if ( this.confirmButton ) {
			this.confirmButton.disabled = true;
		}

		if ( this.passwordError ) {
			this.passwordError.style.display = 'none';
		}

		// Show modal with fade-in effect.
		this.modal.style.display = 'flex';
		setTimeout( () => {
			this.modal.style.opacity = '1';
		}, 10 );

		// Focus on password field.
		setTimeout( () => {
			this.passwordInput?.focus();
		}, 350 );

		// Prevent body scroll.
		document.body.classList.add( 'modal-open' );

		if ( this.deleteButton ) {
			this.deleteButton.setAttribute( 'aria-expanded', 'true' );
		}
	}

	/**
	 * Hide confirmation modal.
	 *
	 * @param {Event} e Click event.
	 */
	hideModal( e ) {
		e?.preventDefault();

		if ( ! this.modal ) {
			return;
		}

		// Fade out.
		this.modal.style.opacity = '0';
		setTimeout( () => {
			this.modal.style.display = 'none';
		}, 300 );

		document.body.classList.remove( 'modal-open' );

		if ( this.deleteButton ) {
			this.deleteButton.setAttribute( 'aria-expanded', 'false' );
			this.deleteButton.focus();
		}

		// Clear any error messages.
		if ( this.passwordError ) {
			this.passwordError.style.display = 'none';
		}
	}

	/**
	 * Validate password input.
	 */
	validatePassword() {
		const password = this.passwordInput?.value || '';

		if ( this.confirmButton ) {
			this.confirmButton.disabled = password.length === 0;
		}

		// Hide any previous error messages.
		if ( this.passwordError ) {
			this.passwordError.style.display = 'none';
		}
	}

	/**
	 * Trap keyboard focus inside the modal.
	 *
	 * @param {KeyboardEvent} e
	 */
	trapFocus( e ) {
		if ( e.key !== 'Tab' ) return;

		const focusable = Array.from(
			this.modal.querySelectorAll( 'button:not([disabled]), input:not([disabled])' )
		);

		if ( focusable.length === 0 ) return;

		const first = focusable[ 0 ];
		const last  = focusable[ focusable.length - 1 ];

		if ( e.shiftKey ) {
			if ( document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			}
		} else {
			if ( document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	}

	/**
	 * Confirm account deletion.
	 *
	 * @param {Event} e Click event.
	 */
	async confirmDeletion( e ) {
		e?.preventDefault();

		const password = this.passwordInput?.value || '';

		// Validate password.
		if ( ! password ) {
			this.showError( this.config.passwordLabel || 'Please enter your password' );
			this.passwordInput?.focus();
			return;
		}

		// Disable button and show processing state.
		const originalText = this.confirmButton.textContent;
		this.confirmButton.disabled = true;
		this.confirmButton.textContent = this.config.processing || 'Processing...';

		try {
			// Use REST API if available, otherwise fall back to AJAX.
			if ( this.config.restUrl && this.config.nonce ) {
				await this.deleteViaREST( password );
			} else {
				await this.deleteViaAJAX( password );
			}
		} catch ( error ) {
			console.error( 'Deletion request failed:', error );
			// Show the actual error message from the server, or fall back to generic message
			this.showError( error.message || this.config.error || 'An error occurred. Please try again.' );
			this.confirmButton.disabled = false;
			this.confirmButton.textContent = originalText;
		}
	}

	/**
	 * Delete account via REST API.
	 *
	 * @param {string} password User password.
	 */
	async deleteViaREST( password ) {
		const response = await fetch( `${this.config.restUrl}/delete-account`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': this.config.nonce,
			},
			body: JSON.stringify( { password } ),
		} );

		const data = await response.json();

		if ( ! response.ok ) {
			// Extract the error message from the WordPress REST API error response
			const errorMessage = data.message || ( data.data && data.data.message ) || 'Request failed';
			throw new Error( errorMessage );
		}

		if ( data.success ) {
			this.showSuccessMessage( data.message || 'Account deleted successfully' );
			setTimeout( () => {
				window.location.href = data.redirect || '/';
			}, 2000 );
		} else {
			throw new Error( data.message || 'An error occurred' );
		}
	}

	/**
	 * Delete account via AJAX (fallback).
	 *
	 * @param {string} password User password.
	 */
	async deleteViaAJAX( password ) {
		const formData = new FormData();
		formData.append( 'action', 'delete_user_account' );
		formData.append( 'nonce', this.config.ajaxNonce );
		formData.append( 'password', password );

		const response = await fetch( this.config.ajaxUrl, {
			method: 'POST',
			body: formData,
		} );

		const data = await response.json();

		if ( data.success ) {
			this.showSuccessMessage( data.data.message || 'Account deleted successfully' );
			setTimeout( () => {
				window.location.href = data.data.redirect || '/';
			}, 2000 );
		} else {
			// Throw error to be caught by try/catch block
			throw new Error( data.data || 'An error occurred' );
		}
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message Error message.
	 */
	showError( message ) {
		if ( ! this.passwordError ) {
			return;
		}

		this.passwordError.textContent = message;
		this.passwordError.style.display = 'block';

		// Focus back to password field.
		this.passwordInput?.focus();
		this.passwordInput?.select();
	}

	/**
	 * Show success message using safe DOM methods.
	 *
	 * @param {string} message Success message.
	 */
	showSuccessMessage( message ) {
		// Hide the modal.
		this.hideModal();

		// Create success notification using safe DOM methods.
		const notification = document.createElement( 'div' );
		notification.className = 'user-delete-success-notification';

		// Create content wrapper.
		const contentDiv = document.createElement( 'div' );
		contentDiv.className = 'success-content';

		// Create strong element for the message.
		const messageStrong = document.createElement( 'strong' );
		messageStrong.textContent = `✓ ${message}`;

		// Create line break.
		const lineBreak = document.createElement( 'br' );

		// Create redirect text.
		const redirectText = document.createTextNode( 'You will be redirected shortly...' );

		// Assemble the content.
		contentDiv.appendChild( messageStrong );
		contentDiv.appendChild( lineBreak );
		contentDiv.appendChild( redirectText );
		notification.appendChild( contentDiv );

		// Apply styles safely.
		Object.assign( notification.style, {
			position: 'fixed',
			top: '20px',
			right: '20px',
			background: '#4CAF50',
			color: 'white',
			padding: '15px 20px',
			borderRadius: '5px',
			boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
			zIndex: '10000',
			maxWidth: '400px',
			fontSize: '14px',
			lineHeight: '1.4',
			opacity: '0',
			transition: 'opacity 0.3s ease',
		} );

		document.body.appendChild( notification );

		// Animate in.
		setTimeout( () => {
			notification.style.opacity = '1';
		}, 10 );
	}

	/**
	 * Log debug information.
	 *
	 * @param {string} message Log message.
	 * @param {*} data Optional data.
	 */
	static log( message, data = null ) {
		if ( console && console.log ) {
			console.log( `[User Self Delete] ${message}`, data || '' );
		}
	}
}

// Initialize when ready.
new UserSelfDelete();

// Make available globally for debugging.
window.UserSelfDeleteInstance = UserSelfDelete;
