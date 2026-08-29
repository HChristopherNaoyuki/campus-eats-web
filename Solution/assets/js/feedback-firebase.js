/**
 * Feedback Firebase Integration Module
 *
 * This module provides Firebase-specific feedback operations for the
 * Campus Eats application.
 *
 * CORRECTIONS (Version 1.0):
 * - Integration with Firebase Realtime Database
 * - Feedback submission with user context
 * - Feedback reading with security enforcement
 * - Admin feedback management
 *
 * SOURCE: Firebase Realtime Database Documentation
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - Submit feedback)
 *
 * @version 1.0
 */

(function()
{
    'use strict';

    // =========================================================================
    // Constants
    // =========================================================================

    const FEEDBACK_TYPES = {
        COMPLAINT: 'complaint',
        COMPLIMENT: 'compliment'
    };

    const FEEDBACK_STATUS = {
        PENDING: 'pending',
        RESOLVED: 'resolved'
    };

    // =========================================================================
    // State
    // =========================================================================

    let currentPHPUserId = null;
    let currentPHPUserRole = null;
    let currentPHPUserName = null;
    let currentPHPUserEmail = null;

    // =========================================================================
    // Initialization
    // =========================================================================

    /**
     * Initializes the feedback module with PHP user context.
     *
     * @param {Object} userContext - PHP user context
     * @param {string} userContext.userId - PHP user ID
     * @param {string} userContext.role - PHP user role
     * @param {string} userContext.fullName - User's full name
     * @param {string} userContext.email - User's email
     */
    function initFeedbackModule(userContext)
    {
        if (userContext)
        {
            currentPHPUserId = userContext.userId || null;
            currentPHPUserRole = userContext.role || null;
            currentPHPUserName = userContext.fullName || null;
            currentPHPUserEmail = userContext.email || null;
        }

        console.log('Feedback module initialized with user context:', userContext);
    }

    /**
     * Gets the current PHP user context.
     *
     * @returns {Object} User context
     */
    function getUserContext()
    {
        return {
            userId: currentPHPUserId,
            role: currentPHPUserRole,
            fullName: currentPHPUserName,
            email: currentPHPUserEmail
        };
    }

    // =========================================================================
    // Feedback Operations
    // =========================================================================

    /**
     * Submits feedback to Firebase.
     *
     * @param {Object} feedbackData - Feedback data
     * @param {string} feedbackData.type - 'complaint' or 'compliment'
     * @param {string} feedbackData.subject - Subject of feedback
     * @param {string} feedbackData.message - Feedback message
     * @returns {Promise} Resolves with feedback ID
     */
    function submitFeedback(feedbackData)
    {
        if (!feedbackData || !feedbackData.type || !feedbackData.subject || !feedbackData.message)
        {
            return Promise.reject(new Error('All feedback fields are required'));
        }

        // Ensure Firebase is initialized
        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                const firebaseUid = user.uid;

                // Prepare feedback data with user context
                const payload = {
                    type: feedbackData.type,
                    subject: feedbackData.subject,
                    message: feedbackData.message,
                    userId: firebaseUid,
                    userName: currentPHPUserName || 'User',
                    userEmail: currentPHPUserEmail || '',
                    phpUserId: currentPHPUserId || '',
                    phpUserRole: currentPHPUserRole || '',
                    status: FEEDBACK_STATUS.PENDING,
                    createdAt: new Date().toISOString(),
                    updatedAt: new Date().toISOString()
                };

                return window.Firebase.submitFeedback(payload);
            })
            .then(function(feedbackId)
            {
                console.log('Feedback submitted successfully. ID:', feedbackId);
                return feedbackId;
            })
            .catch(function(error)
            {
                console.error('Feedback submission failed:', error);
                throw error;
            });
    }

    /**
     * Reads feedback for the current user.
     *
     * @param {boolean} includeAdmin - Whether to include admin-only feedback
     * @returns {Promise} Array of feedback entries
     */
    function getMyFeedback(includeAdmin)
    {
        includeAdmin = includeAdmin || false;

        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                const firebaseUid = user.uid;

                return window.Firebase.readFeedback(firebaseUid, true);
            })
            .then(function(feedbackList)
            {
                // Additional client-side filtering
                // Server-side rules already enforce security
                return feedbackList;
            })
            .catch(function(error)
            {
                console.error('Failed to read feedback:', error);
                throw error;
            });
    }

    /**
     * Reads all feedback (admin only).
     *
     * @returns {Promise} Array of all feedback entries
     */
    function getAllFeedback()
    {
        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                // Check if user is admin in Firebase
                return window.Firebase.isFirebaseAdmin(user.uid)
                    .then(function(isAdmin)
                    {
                        if (!isAdmin)
                        {
                            throw new Error('Admin access required');
                        }

                        return window.Firebase.readFeedback(null, false);
                    });
            })
            .catch(function(error)
            {
                console.error('Failed to read all feedback:', error);
                throw error;
            });
    }

    /**
     * Resolves feedback (admin only).
     *
     * @param {string} feedbackId - Firebase feedback key
     * @returns {Promise} Resolves when updated
     */
    function resolveFeedback(feedbackId)
    {
        if (!feedbackId)
        {
            return Promise.reject(new Error('Feedback ID is required'));
        }

        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                // Check if user is admin in Firebase
                return window.Firebase.isFirebaseAdmin(user.uid)
                    .then(function(isAdmin)
                    {
                        if (!isAdmin)
                        {
                            throw new Error('Admin access required to resolve feedback');
                        }

                        return window.Firebase.updateFeedbackStatus(feedbackId, FEEDBACK_STATUS.RESOLVED, user.uid);
                    });
            })
            .then(function()
            {
                console.log('Feedback resolved successfully. ID:', feedbackId);
                return feedbackId;
            })
            .catch(function(error)
            {
                console.error('Failed to resolve feedback:', error);
                throw error;
            });
    }

    /**
     * Unresolves feedback (admin only).
     *
     * @param {string} feedbackId - Firebase feedback key
     * @returns {Promise} Resolves when updated
     */
    function unresolveFeedback(feedbackId)
    {
        if (!feedbackId)
        {
            return Promise.reject(new Error('Feedback ID is required'));
        }

        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                return window.Firebase.isFirebaseAdmin(user.uid)
                    .then(function(isAdmin)
                    {
                        if (!isAdmin)
                        {
                            throw new Error('Admin access required to unresolve feedback');
                        }

                        return window.Firebase.updateFeedbackStatus(feedbackId, FEEDBACK_STATUS.PENDING, user.uid);
                    });
            })
            .then(function()
            {
                console.log('Feedback unresolved successfully. ID:', feedbackId);
                return feedbackId;
            })
            .catch(function(error)
            {
                console.error('Failed to unresolve feedback:', error);
                throw error;
            });
    }

    /**
     * Gets the count of pending feedback (admin only).
     *
     * @returns {Promise<number>} Count of pending feedback
     */
    function getPendingFeedbackCount()
    {
        return getAllFeedback()
            .then(function(feedbackList)
            {
                if (!feedbackList || !Array.isArray(feedbackList))
                {
                    return 0;
                }

                return feedbackList.filter(function(item)
                {
                    return item.status === FEEDBACK_STATUS.PENDING;
                }).length;
            })
            .catch(function()
            {
                return 0;
            });
    }

    // =========================================================================
    // Public API
    // =========================================================================

    const FeedbackAPI = {
        init: initFeedbackModule,
        getUserContext: getUserContext,
        submitFeedback: submitFeedback,
        getMyFeedback: getMyFeedback,
        getAllFeedback: getAllFeedback,
        resolveFeedback: resolveFeedback,
        unresolveFeedback: unresolveFeedback,
        getPendingFeedbackCount: getPendingFeedbackCount,
        FEEDBACK_TYPES: FEEDBACK_TYPES,
        FEEDBACK_STATUS: FEEDBACK_STATUS
    };

    // =========================================================================
    // Expose API globally
    // =========================================================================

    window.Feedback = FeedbackAPI;

    console.log('Feedback Firebase module loaded successfully');
})();