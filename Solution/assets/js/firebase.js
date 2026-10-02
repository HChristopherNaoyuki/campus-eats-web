/**
 * Firebase Integration Module for Campus Eats
 *
 * Initializes the Firebase Web SDK and provides shared functionality
 * for Firebase Realtime Database operations.
 *
 * CORRECTIONS (Version 5.0 - Technical Audit Report):
 * - Restored writeData(path, data, requireAuth). The function was
 *   removed in Version 4.0, which broke the synchronization loop in
 *   firebase-sync.js. The synchronization loop depends on this
 *   function to project the current page state to the database.
 * - Added updateData(path, partial, requireAuth) for partial writes.
 * - Added a client-side allow-list of permitted path prefixes: sync/,
 *   feedback/, and users/. The function rejects paths outside this
 *   list. This is defence in depth. The final enforcement remains the
 *   Firebase security rules and Firebase Authentication.
 * - Paths are rejected if they contain '..' or begin with '/'.
 * - Retained readData() and readFeedback() from Version 4.0.
 * - Retained the corrected ensureAuthenticated() behaviour that
 *   respects the caller's allowAnonymous argument.
 *
 * SOURCE: Campus Eats Technical Audit Report, Section 3.2.
 * SOURCE: Review items 7, 8, 18, 20.
 *
 * @version 5.0
 */

(function()
{
    'use strict';

    var DB_PATHS = {
        USERS: 'users',
        FEEDBACK: 'feedback',
        ADMIN_CLAIMS: 'admin_claims',
        SYNC: 'sync'
    };

    // =========================================================================
    // Client-Side Write Allow-List
    // =========================================================================
    //
    // The synchronization loop writes to sync/{uid}. The feedback
    // submission path writes to feedback/{key}. A future profile update
    // path may write to users/{userId}. No other path is permitted from
    // the client. This list is a defence-in-depth measure. The Firebase
    // security rules are the authoritative control.
    // =========================================================================

    var ALLOWED_WRITE_PREFIXES = [
        'sync/',
        'feedback/',
        'users/'
    ];

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
     * The allowAnonymous parameter is respected. When the caller passes
     * false and no user is signed in, the function rejects. The previous
     * version forced the flag to true, which was incorrect.
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
     * Validates a path against the client-side allow-list.
     *
     * A path is rejected when it is empty, begins with a slash, contains
     * a '..' segment, or does not begin with one of the permitted
     * prefixes. The function returns a normalised path on success.
     *
     * @param {string} path The path to validate
     * @returns {string} The normalised path
     * @throws {Error} When the path is not permitted
     */
    function validateWritePath(path)
    {
        if (typeof path !== 'string' || path === '')
        {
            throw new Error('Write path must be a non-empty string.');
        }

        if (path.charAt(0) === '/')
        {
            throw new Error('Write path must be relative.');
        }

        if (path.indexOf('..') !== -1)
        {
            throw new Error('Write path must not contain parent segments.');
        }

        var permitted = false;

        for (var i = 0; i < ALLOWED_WRITE_PREFIXES.length; i++)
        {
            if (path.indexOf(ALLOWED_WRITE_PREFIXES[i]) === 0)
            {
                permitted = true;
                break;
            }
        }

        if (!permitted)
        {
            throw new Error(
                'Write path is not in the client-side allow-list: ' + path
            );
        }

        return path;
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
     * Writes a value to the Realtime Database.
     *
     * The path is validated against the client-side allow-list before the
     * write is attempted. The final enforcement is the Firebase security
     * rules. When requireAuth is true and no user is signed in, the
     * function rejects.
     *
     * This function was removed in Version 4.0. Its absence broke the
     * synchronization loop in firebase-sync.js. It is restored here.
     *
     * @param {string} path The database path
     * @param {*} data The value to write
     * @param {boolean} requireAuth Whether to require authentication
     * @returns {Promise<void>}
     */
    function writeData(path, data, requireAuth)
    {
        if (typeof requireAuth === 'undefined')
        {
            requireAuth = true;
        }

        try
        {
            validateWritePath(path);
        }
        catch (validationError)
        {
            return Promise.reject(validationError);
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
                var setFn = module.set;
                var dbRef = refFn(firebaseDatabase, path);
                return setFn(dbRef, data);
            });
    }

    /**
     * Updates part of a value at a database path.
     *
     * The path is validated against the client-side allow-list. The
     * partial object is merged into the existing value at the path.
     *
     * @param {string} path The database path
     * @param {Object} partial The fields to update
     * @param {boolean} requireAuth Whether to require authentication
     * @returns {Promise<void>}
     */
    function updateData(path, partial, requireAuth)
    {
        if (typeof requireAuth === 'undefined')
        {
            requireAuth = true;
        }

        try
        {
            validateWritePath(path);
        }
        catch (validationError)
        {
            return Promise.reject(validationError);
        }

        if (!partial || typeof partial !== 'object')
        {
            return Promise.reject(new Error('Update data must be an object.'));
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
                var updateFn = module.update;
                var dbRef = refFn(firebaseDatabase, path);
                return updateFn(dbRef, partial);
            });
    }

    /**
     * Reads feedback entries.
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
        writeData: writeData,
        updateData: updateData,
        readFeedback: readFeedback,
        validateWritePath: validateWritePath,
        DB_PATHS: DB_PATHS,
        ALLOWED_WRITE_PREFIXES: ALLOWED_WRITE_PREFIXES,
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