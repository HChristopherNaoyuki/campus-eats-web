/**
 * Firebase Integration Module for Campus Eats
 *
 * Initializes the Firebase Web SDK and provides shared functionality
 * for Firebase Realtime Database operations.
 *
 * CORRECTIONS (Version 4.0):
 * - Removed the writeData() function and the deleteData() function.
 *   These were never called by any page in the codebase. Removing them
 *   eliminates dead code and removes any accidental path by which a
 *   client could attempt a protected write.
 * - Fixed the "param = param || true" pattern in ensureAuthenticated().
 *   The previous version forced the allowAnonymous flag to true even
 *   when the caller passed false. The corrected version only defaults
 *   the flag when the parameter is undefined.
 * - readFeedback() now reads the whole feedback node and filters by
 *   userId. This is the only read the application performs.
 * - The Firebase configuration is fetched from /api/firebase_config.php
 *   rather than being hardcoded. The PHP side is the single source of
 *   truth for the client configuration.
 * - Firebase SDK version is taken from the server response. If the
 *   response does not specify a version, it falls back to 12.18.0.
 *
 * SOURCE: Review items 7, 8, 18, 20
 *
 * @version 4.0
 */

(function()
{
    'use strict';

    var DB_PATHS = {
        USERS: 'users',
        FEEDBACK: 'feedback',
        ADMIN_CLAIMS: 'admin_claims'
    };

    var firebaseApp = null;
    var firebaseDatabase = null;
    var firebaseAuth = null;
    var currentUser = null;
    var isInitialized = false;
    var initPromise = null;
    var firebaseConfig = null;

    /**
     * Fetches the Firebase configuration from the PHP endpoint.
     *
     * @returns {Promise<Object>} Resolves with the configuration
     */
    function loadFirebaseConfig()
    {
        var baseUrl = (typeof window.BASE_URL !== 'undefined' && window.BASE_URL)
            ? window.BASE_URL
            : '';

        var url = baseUrl + '/api/firebase_config.php';

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response)
        {
            if (!response.ok)
            {
                throw new Error('Failed to fetch Firebase config: ' + response.status);
            }
            return response.json();
        })
        .then(function(data)
        {
            if (!data.success || !data.config)
            {
                throw new Error(data.message || 'Firebase config response invalid');
            }
            firebaseConfig = data.config;
            return firebaseConfig;
        });
    }

    /**
     * Initializes Firebase once per page.
     *
     * @returns {Promise<void>}
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

        initPromise = loadFirebaseConfig()
            .then(function(config)
            {
                var version = config.sdkVersion || '12.18.0';
                var baseUrl = 'https://www.gstatic.com/firebasejs/' + version + '/';

                return Promise.all([
                    import(baseUrl + 'firebase-app.js'),
                    import(baseUrl + 'firebase-database.js'),
                    import(baseUrl + 'firebase-auth.js')
                ]);
            })
            .then(function(modules)
            {
                var appModule = modules[0];
                var databaseModule = modules[1];
                var authModule = modules[2];

                firebaseApp = appModule.initializeApp(firebaseConfig);
                firebaseDatabase = databaseModule.getDatabase(firebaseApp);
                firebaseAuth = authModule.getAuth(firebaseApp);

                isInitialized = true;

                authModule.onAuthStateChanged(firebaseAuth, function(user)
                {
                    if (user)
                    {
                        currentUser = user;
                        document.dispatchEvent(new CustomEvent('firebase-auth-changed', {
                            detail: { authenticated: true, user: user }
                        }));
                    }
                    else
                    {
                        currentUser = null;
                        document.dispatchEvent(new CustomEvent('firebase-auth-changed', {
                            detail: { authenticated: false, user: null }
                        }));
                    }
                });
            })
            .catch(function(error)
            {
                console.error('Firebase: Initialization failed:', error);
                isInitialized = false;
                initPromise = null;
                throw error;
            });

        return initPromise;
    }

    /**
     * Returns the current Firebase user, or null.
     *
     * @returns {Object|null}
     */
    function getCurrentUser()
    {
        return currentUser;
    }

    /**
     * Checks if a user is currently authenticated with Firebase.
     *
     * @returns {boolean}
     */
    function isAuthenticated()
    {
        return currentUser !== null;
    }

    /**
     * Signs in anonymously to Firebase.
     *
     * @returns {Promise<Object>} Resolves with the sign-in result
     */
    function signInAnonymously()
    {
        if (!firebaseAuth)
        {
            return Promise.reject(new Error('Firebase auth not initialized'));
        }

        return import(
            'https://www.gstatic.com/firebasejs/' +
            (firebaseConfig.sdkVersion || '12.18.0') +
            '/firebase-auth.js'
        )
        .then(function(module)
        {
            return module.signInAnonymously(firebaseAuth);
        })
        .then(function(result)
        {
            currentUser = result.user;
            return result;
        });
    }

    /**
     * Ensures Firebase is authenticated before performing an operation.
     *
     * CORRECTION: The previous implementation used
     * "allowAnonymous = allowAnonymous || true;" which forced the flag to
     * true even when the caller passed false. This version uses a proper
     * default and respects the caller's value.
     *
     * @param {boolean} allowAnonymous Whether to allow anonymous sign-in
     * @returns {Promise<Object>} Resolves with the authenticated user
     */
    function ensureAuthenticated(allowAnonymous)
    {
        if (typeof allowAnonymous === 'undefined')
        {
            allowAnonymous = true;
        }

        return initializeFirebase()
            .then(function()
            {
                if (isAuthenticated())
                {
                    return Promise.resolve(currentUser);
                }

                if (allowAnonymous)
                {
                    return signInAnonymously().then(function(result)
                    {
                        return result.user;
                    });
                }

                return Promise.reject(new Error('No authenticated user'));
            });
    }

    /**
     * Returns the Firebase UID for the current user.
     *
     * @param {boolean} requireAuth Whether to require authentication
     * @returns {Promise<string|null>}
     */
    function getFirebaseUid(requireAuth)
    {
        if (typeof requireAuth === 'undefined')
        {
            requireAuth = true;
        }

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
     * Reads a value from the Realtime Database.
     *
     * @param {string} path Database path
     * @param {string|null} childKey Optional child key
     * @param {boolean} requireAuth Whether to require authentication
     * @returns {Promise<*>}
     */
    function readData(path, childKey, requireAuth)
    {
        if (typeof requireAuth === 'undefined')
        {
            requireAuth = true;
        }

        return ensureAuthenticated(requireAuth)
            .then(function()
            {
                return import(
                    'https://www.gstatic.com/firebasejs/' +
                    (firebaseConfig.sdkVersion || '12.18.0') +
                    '/firebase-database.js'
                );
            })
            .then(function(module)
            {
                var refFn = module.ref;
                var getFn = module.get;
                var fullPath = childKey ? (path + '/' + childKey) : path;
                var dbRef = refFn(firebaseDatabase, fullPath);
                return getFn(dbRef);
            })
            .then(function(snapshot)
            {
                return snapshot.val();
            });
    }

    /**
     * Reads feedback entries.
     *
     * CORRECTION: "onlyUser" now defaults properly to true only when the
     * parameter is undefined. Callers may pass false to read all feedback
     * subject to the Firebase security rules.
     *
     * @param {string|null} userId Firebase UID to filter by
     * @param {boolean} onlyUser If true, filter to the given user only
     * @returns {Promise<Array>}
     */
    function readFeedback(userId, onlyUser)
    {
        if (typeof onlyUser === 'undefined')
        {
            onlyUser = true;
        }

        return readData('feedback', null, true)
            .then(function(data)
            {
                if (!data)
                {
                    return [];
                }

                var feedbackList = [];
                var keys = Object.keys(data);

                for (var i = 0; i < keys.length; i++)
                {
                    var entry = data[keys[i]];
                    entry._id = keys[i];

                    if (onlyUser && userId && entry.userId !== userId)
                    {
                        continue;
                    }

                    feedbackList.push(entry);
                }

                feedbackList.sort(function(a, b)
                {
                    return new Date(b.createdAt) - new Date(a.createdAt);
                });

                return feedbackList;
            });
    }

    var FirebaseAPI = {
        initialize: initializeFirebase,
        getCurrentUser: getCurrentUser,
        isAuthenticated: isAuthenticated,
        getFirebaseUid: getFirebaseUid,
        signInAnonymously: signInAnonymously,
        ensureAuthenticated: ensureAuthenticated,
        readData: readData,
        readFeedback: readFeedback,
        DB_PATHS: DB_PATHS,
        isInitialized: function() { return isInitialized; }
    };

    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', function()
        {
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

    window.Firebase = FirebaseAPI;
})();