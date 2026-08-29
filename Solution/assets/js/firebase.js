/**
 * Firebase Integration Module for Campus Eats
 *
 * This module initializes the Firebase SDK and provides shared functionality
 * for Firebase Realtime Database operations.
 *
 * CORRECTIONS (Version 1.0):
 * - Centralized Firebase initialization
 * - Handles authentication state
 * - Provides database reference functions
 * - Includes error handling
 *
 * SOURCE: Firebase Web SDK Documentation
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - Feedback)
 *
 * @version 1.0
 */

(function()
{
    'use strict';

    // =========================================================================
    // Constants
    // =========================================================================

    const FIREBASE_CONFIG = {
        apiKey: 'AIzaSyBnal57F8ODfUHY4CCNpRUIvOEKGmd6T5M',
        authDomain: 'campus-eats-db.firebaseapp.com',
        databaseURL: 'https://campus-eats-db-default-rtdb.europe-west1.firebasedatabase.app',
        projectId: 'campus-eats-db',
        storageBucket: 'campus-eats-db.firebasestorage.app',
        messagingSenderId: '64265928399',
        appId: '1:64265928399:web:1ed0d7f032fbdad34b01ba',
        measurementId: 'G-8MSCNGN1XD'
    };

    const DB_PATHS = {
        USERS: 'users',
        FEEDBACK: 'feedback',
        ADMIN_CLAIMS: 'admin_claims'
    };

    // =========================================================================
    // State
    // =========================================================================

    let firebaseApp = null;
    let firebaseDatabase = null;
    let firebaseAuth = null;
    let currentUser = null;
    let isInitialized = false;
    let initPromise = null;

    // =========================================================================
    // Core Functions
    // =========================================================================

    /**
     * Initializes Firebase and returns a promise that resolves when ready.
     *
     * @returns {Promise} Resolves when Firebase is initialized
     */
    function initializeFirebase()
    {
        if (isInitialized)
        {
            return Promise.resolve();
        }

        if (initPromise)
        {
            return initPromise;
        }

        initPromise = new Promise(function(resolve, reject)
        {
            try
            {
                // Dynamically import Firebase modules
                Promise.all([
                    import('https://www.gstatic.com/firebasejs/12.18.0/firebase-app.js'),
                    import('https://www.gstatic.com/firebasejs/12.18.0/firebase-database.js'),
                    import('https://www.gstatic.com/firebasejs/12.18.0/firebase-auth.js')
                ])
                .then(function(modules)
                {
                    const appModule = modules[0];
                    const databaseModule = modules[1];
                    const authModule = modules[2];

                    // Initialize Firebase App
                    firebaseApp = appModule.initializeApp(FIREBASE_CONFIG);
                    firebaseDatabase = databaseModule.getDatabase(firebaseApp);
                    firebaseAuth = authModule.getAuth(firebaseApp);

                    isInitialized = true;

                    // Set up auth state listener
                    authModule.onAuthStateChanged(firebaseAuth, function(user)
                    {
                        if (user)
                        {
                            currentUser = user;
                            console.log('Firebase: User authenticated:', user.uid);
                            document.dispatchEvent(new CustomEvent('firebase-auth-changed',
                            {
                                detail: { authenticated: true, user: user }
                            }));
                        }
                        else
                        {
                            currentUser = null;
                            console.log('Firebase: No authenticated user');
                            document.dispatchEvent(new CustomEvent('firebase-auth-changed',
                            {
                                detail: { authenticated: false, user: null }
                            }));
                        }
                    });

                    console.log('Firebase: Initialized successfully');
                    resolve();
                })
                .catch(function(error)
                {
                    console.error('Firebase: Initialization failed:', error);
                    isInitialized = false;
                    initPromise = null;
                    reject(error);
                });
            }
            catch (error)
            {
                console.error('Firebase: Initialization error:', error);
                isInitialized = false;
                initPromise = null;
                reject(error);
            }
        });

        return initPromise;
    }

    /**
     * Returns the Firebase database reference.
     *
     * @param {string} path - Optional path to reference
     * @returns {object|null} Database reference or null if not initialized
     */
    function getDatabaseReference(path)
    {
        if (!firebaseDatabase)
        {
            console.error('Firebase: Database not initialized');
            return null;
        }

        // Dynamically import database module for ref function
        return import('https://www.gstatic.com/firebasejs/12.18.0/firebase-database.js')
            .then(function(module)
            {
                const refFn = module.ref;
                const db = firebaseDatabase;

                if (path)
                {
                    return refFn(db, path);
                }

                return refFn(db);
            })
            .catch(function(error)
            {
                console.error('Firebase: Failed to get database reference:', error);
                return null;
            });
    }

    /**
     * Returns the Firebase auth instance.
     *
     * @returns {object|null} Auth instance or null if not initialized
     */
    function getAuth()
    {
        return firebaseAuth;
    }

    /**
     * Returns the current Firebase user.
     *
     * @returns {object|null} Current user or null
     */
    function getCurrentUser()
    {
        return currentUser;
    }

    /**
     * Checks if the current user is authenticated with Firebase.
     *
     * @returns {boolean} True if authenticated
     */
    function isAuthenticated()
    {
        return currentUser !== null;
    }

    /**
     * Signs in anonymously to Firebase (for unauthenticated users).
     *
     * @returns {Promise} Resolves when signed in
     */
    function signInAnonymously()
    {
        if (!firebaseAuth)
        {
            return Promise.reject(new Error('Firebase auth not initialized'));
        }

        return import('https://www.gstatic.com/firebasejs/12.18.0/firebase-auth.js')
            .then(function(module)
            {
                return module.signInAnonymously(firebaseAuth);
            })
            .then(function(result)
            {
                currentUser = result.user;
                console.log('Firebase: Signed in anonymously as:', currentUser.uid);
                return result;
            })
            .catch(function(error)
            {
                console.error('Firebase: Anonymous sign-in failed:', error);
                throw error;
            });
    }

    /**
     * Ensures Firebase is authenticated before performing operations.
     *
     * @param {boolean} allowAnonymous - Whether to allow anonymous auth
     * @returns {Promise} Resolves when authenticated
     */
    function ensureAuthenticated(allowAnonymous)
    {
        allowAnonymous = allowAnonymous || true;

        return initializeFirebase()
            .then(function()
            {
                if (isAuthenticated())
                {
                    return Promise.resolve(currentUser);
                }

                if (allowAnonymous)
                {
                    return signInAnonymously();
                }

                return Promise.reject(new Error('No authenticated user'));
            });
    }

    /**
     * Gets the Firebase UID for the current user.
     *
     * @param {boolean} requireAuth - Whether to require authentication
     * @returns {Promise<string>} Firebase UID
     */
    function getFirebaseUid(requireAuth)
    {
        requireAuth = requireAuth || true;

        return ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user && requireAuth)
                {
                    throw new Error('No authenticated user');
                }

                return user ? user.uid : null;
            });
    }

    /**
     * Performs a Firebase database read operation.
     *
     * @param {string} path - Database path
     * @param {string} childKey - Optional child key
     * @param {boolean} requireAuth - Whether to require authentication
     * @returns {Promise} Data from Firebase
     */
    function readData(path, childKey, requireAuth)
    {
        requireAuth = requireAuth || true;

        return ensureAuthenticated(requireAuth)
            .then(function()
            {
                return import('https://www.gstatic.com/firebasejs/12.18.0/firebase-database.js');
            })
            .then(function(module)
            {
                const refFn = module.ref;
                const getFn = module.get;
                const db = firebaseDatabase;

                let fullPath = path;

                if (childKey)
                {
                    fullPath = path + '/' + childKey;
                }

                const dbRef = refFn(db, fullPath);

                return getFn(dbRef);
            })
            .then(function(snapshot)
            {
                return snapshot.val();
            })
            .catch(function(error)
            {
                console.error('Firebase: Read operation failed:', error);
                throw error;
            });
    }

    /**
     * Performs a Firebase database write operation.
     *
     * @param {string} path - Database path
     * @param {*} data - Data to write
     * @param {string} childKey - Optional child key
     * @param {boolean} requireAuth - Whether to require authentication
     * @returns {Promise} Resolves when write completes
     */
    function writeData(path, data, childKey, requireAuth)
    {
        requireAuth = requireAuth || true;

        return ensureAuthenticated(requireAuth)
            .then(function(user)
            {
                if (!user && requireAuth)
                {
                    throw new Error('No authenticated user');
                }

                return import('https://www.gstatic.com/firebasejs/12.18.0/firebase-database.js');
            })
            .then(function(module)
            {
                const refFn = module.ref;
                const setFn = module.set;
                const pushFn = module.push;
                const db = firebaseDatabase;

                let dbRef;

                if (childKey)
                {
                    const fullPath = path + '/' + childKey;
                    dbRef = refFn(db, fullPath);
                    return setFn(dbRef, data);
                }
                else
                {
                    dbRef = refFn(db, path);
                    // Use push for auto-generated keys when no childKey
                    const newRef = pushFn(dbRef);
                    return setFn(newRef, data).then(function()
                    {
                        return newRef.key;
                    });
                }
            })
            .then(function(result)
            {
                return result;
            })
            .catch(function(error)
            {
                console.error('Firebase: Write operation failed:', error);
                throw error;
            });
    }

    /**
     * Deletes data from Firebase.
     *
     * @param {string} path - Database path
     * @param {string} childKey - Child key to delete
     * @param {boolean} requireAuth - Whether to require authentication
     * @returns {Promise} Resolves when delete completes
     */
    function deleteData(path, childKey, requireAuth)
    {
        requireAuth = requireAuth || true;

        return ensureAuthenticated(requireAuth)
            .then(function()
            {
                return import('https://www.gstatic.com/firebasejs/12.18.0/firebase-database.js');
            })
            .then(function(module)
            {
                const refFn = module.ref;
                const removeFn = module.remove;
                const db = firebaseDatabase;

                const fullPath = path + '/' + childKey;
                const dbRef = refFn(db, fullPath);

                return removeFn(dbRef);
            })
            .catch(function(error)
            {
                console.error('Firebase: Delete operation failed:', error);
                throw error;
            });
    }

    // =========================================================================
    // Feedback-Specific Functions
    // =========================================================================

    /**
     * Submits feedback to Firebase.
     *
     * @param {Object} feedbackData - Feedback data
     * @param {string} feedbackData.type - 'complaint' or 'compliment'
     * @param {string} feedbackData.subject - Feedback subject
     * @param {string} feedbackData.message - Feedback message
     * @param {string} feedbackData.userId - Firebase UID
     * @param {string} feedbackData.userName - User's full name
     * @param {string} feedbackData.userEmail - User's email
     * @param {string} feedbackData.phpUserId - PHP user ID (for reference)
     * @param {string} feedbackData.phpUserRole - PHP user role
     * @param {string} feedbackData.status - 'pending', 'resolved'
     * @returns {Promise} Resolves with feedback ID
     */
    function submitFeedback(feedbackData)
    {
        const requiredFields = ['type', 'subject', 'message', 'userId', 'userName', 'userEmail'];

        for (let i = 0; i < requiredFields.length; i++)
        {
            const field = requiredFields[i];

            if (!feedbackData[field])
            {
                return Promise.reject(new Error('Missing required field: ' + field));
            }
        }

        const feedback = {
            userId: feedbackData.userId,
            type: feedbackData.type,
            subject: feedbackData.subject,
            message: feedbackData.message,
            userName: feedbackData.userName,
            userEmail: feedbackData.userEmail,
            phpUserId: feedbackData.phpUserId || '',
            phpUserRole: feedbackData.phpUserRole || '',
            status: feedbackData.status || 'pending',
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };

        return writeData('feedback', feedback, null, true)
            .then(function(feedbackId)
            {
                return feedbackId;
            });
    }

    /**
     * Reads feedback from Firebase.
     *
     * @param {string} userId - Firebase UID (null for admin read all)
     * @param {boolean} onlyUser - Whether to read only user's feedback
     * @returns {Promise} Feedback data
     */
    function readFeedback(userId, onlyUser)
    {
        onlyUser = onlyUser || true;

        return readData('feedback', null, true)
            .then(function(data)
            {
                if (!data)
                {
                    return [];
                }

                const feedbackList = [];
                const keys = Object.keys(data);

                for (let i = 0; i < keys.length; i++)
                {
                    const key = keys[i];
                    const entry = data[key];
                    entry._id = key;

                    // Security rule enforcement on client side
                    // Note: Server-side rules also enforce this
                    if (onlyUser && userId && entry.userId !== userId)
                    {
                        continue;
                    }

                    feedbackList.push(entry);
                }

                // Sort by createdAt descending
                feedbackList.sort(function(a, b)
                {
                    return new Date(b.createdAt) - new Date(a.createdAt);
                });

                return feedbackList;
            });
    }

    /**
     * Updates feedback status in Firebase.
     *
     * @param {string} feedbackId - Firebase feedback key
     * @param {string} newStatus - New status ('pending', 'resolved')
     * @param {string} userId - Firebase UID (for authorization)
     * @returns {Promise} Resolves when update completes
     */
    function updateFeedbackStatus(feedbackId, newStatus, userId)
    {
        if (!feedbackId || !newStatus)
        {
            return Promise.reject(new Error('Feedback ID and status are required'));
        }

        // Security: Only admins can resolve feedback
        // This is enforced by Firebase rules with admin_claims

        return writeData('feedback/' + feedbackId, { status: newStatus, updatedAt: new Date().toISOString() }, null, true)
            .catch(function(error)
            {
                console.error('Firebase: Failed to update feedback status:', error);
                throw error;
            });
    }

    /**
     * Checks if a user is an admin in Firebase.
     *
     * @param {string} uid - Firebase UID
     * @returns {Promise<boolean>} True if admin
     */
    function isFirebaseAdmin(uid)
    {
        if (!uid)
        {
            return Promise.resolve(false);
        }

        return readData('admin_claims/' + uid, null, true)
            .then(function(data)
            {
                return data === true;
            })
            .catch(function()
            {
                return false;
            });
    }

    // =========================================================================
    // Public API
    // =========================================================================

    const FirebaseAPI = {
        initialize: initializeFirebase,
        getAuth: getAuth,
        getCurrentUser: getCurrentUser,
        isAuthenticated: isAuthenticated,
        getFirebaseUid: getFirebaseUid,
        signInAnonymously: signInAnonymously,
        ensureAuthenticated: ensureAuthenticated,
        readData: readData,
        writeData: writeData,
        deleteData: deleteData,
        submitFeedback: submitFeedback,
        readFeedback: readFeedback,
        updateFeedbackStatus: updateFeedbackStatus,
        isFirebaseAdmin: isFirebaseAdmin,
        DB_PATHS: DB_PATHS,
        isInitialized: function() { return isInitialized; }
    };

    // =========================================================================
    // Auto-Initialize on DOM Ready
    // =========================================================================

    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', function()
        {
            // Initialize Firebase silently - don't block page rendering
            initializeFirebase().catch(function(error)
            {
                console.warn('Firebase: Auto-initialization warning:', error.message);
            });
        });
    }
    else
    {
        initializeFirebase().catch(function(error)
        {
            console.warn('Firebase: Auto-initialization warning:', error.message);
        });
    }

    // =========================================================================
    // Expose API globally
    // =========================================================================

    window.Firebase = FirebaseAPI;

    console.log('Firebase: Module loaded successfully');
})();