# Contributing

## Table of Contents

1.  [Introduction](#1-introduction)
2.  [How to Contribute](#2-how-to-contribute)
3.  [Pull Request Process](#3-pull-request-process)
4.  [Code Style Guidelines](#4-code-style-guidelines)
5.  [Security Requirements](#5-security-requirements)
6.  [Commit Message Format](#6-commit-message-format)
7.  [Testing Requirements](#7-testing-requirements)
8.  [Documentation Standards](#8-documentation-standards)

---

## 1. Introduction

Thank you for your interest in contributing to the Campus Eats project.
This document outlines the guidelines and standards that all
contributors must follow. Adherence to these guidelines ensures that
the code-base remains consistent, maintainable, and secure for all users.

The Campus Eats system is a web-based food ordering platform designed
for a single higher education campus. It connects students with campus
vendors through a digital ordering and pickup management system.

All contributions must align with the requirements and specifications
outlined in the campus-eats-process-document.pdf. The system follows a
minimalist design philosophy with a consistent orange and gray color
scheme.

---

## 2. How to Contribute

Follow these steps to contribute code, documentation, or bug fixes to
the Campus Eats project.

### 2.1. Fork the Repository

Begin by forking the main project repository to your own GitHub
account. Navigate to the repository page and click the Fork button.
This creates a personal copy where you can make changes without
affecting the original codebase.

### 2.2. Clone Your Fork

Clone your forked repository to your local development machine using
the following command.

```
git clone https://github.com/HChristopherNaoyuki/campus-eats-web.git
cd campus-eats-web
```

### 2.3. Create a Feature Branch

Create a new branch for your work using a descriptive name that
reflects the purpose of your changes. Use the following naming
conventions.

-   Feature: `feature/your-feature-name`
-   Bug Fix: `bugfix/issue-number-description`
-   Documentation: `docs/your-doc-update`

```
git checkout -b feature/your-feature-name
```

### 2.4. Make Your Changes

Make your modifications following the coding standards documented
below. Ensure that all changes are tested locally and do not break
existing functionality. Each change must be validated against the
process document to ensure compliance with assessment criteria.

Do not introduce new classes and files. All user interface enhancements
must be achieved by extending and improving the existing activities and
fragments. Reuse and refinement of the current structure are mandatory.

### 2.5. Test Locally

Set up the Campus Eats system locally using the installation
instructions in the main documentation. Test your changes thoroughly
before submitting a pull request. Ensure that all database migrations
work correctly and that the user interface remains responsive.

### 2.6. Push and Submit a Pull Request

Once your changes are complete and tested locally, push your branch to
your fork on GitHub. Then open a pull request against the main branch
of the original repository. Provide a clear description of what you
changed and why.

```
git push origin feature/your-feature-name
```

All contributions will be reviewed by project maintainers. Feedback may
be provided requesting additional changes. After approval, your pull
request will be merged.

---

## 3. Pull Request Process

Pull requests must pass all automated checks before review. The
following requirements must be satisfied for a pull request to be
accepted.

### 3.1. Clear Description

The pull request description must explain the problem being solved and
the approach taken. Reference any related issues by number. The
description should include the following sections.

-   **Summary:** A brief overview of the changes.
-   **Problem:** The issue or feature request being addressed.
-   **Solution:** The approach taken to implement the change.
-   **Testing:** How the changes were tested.
-   **Screenshots:** For UI changes, refer to images stored in the
    project's images folder. Do not embed images directly in the
    description.

### 3.2. Focused Changes

Keep pull requests focused on a single change. Large pull requests that
address multiple unrelated issues are difficult to review and will be
rejected. Split complex changes into smaller, logical pull requests.

### 3.3. No New Classes or Files

Do not introduce new classes and files. All user interface
enhancements must be achieved by extending and improving the existing
activities and fragments. Reuse and refinement of the current structure
are mandatory.

### 3.4. Code Review Process

Pull requests undergo a thorough code review process. Maintainers will
examine the code for adherence to standards, security, and performance.
Feedback may be provided requesting additional changes. All review
comments must be addressed before the pull request can be merged.

### 3.5. Merging Requirements

A pull request is eligible for merging when the following conditions
are met.

-   All automated tests pass.
-   At least one maintainer has approved the changes.
-   All review comments have been resolved.
-   The branch is up to date with the main branch.
-   The commit history is clean and logical.

---

## 4. Code Style Guidelines

All code must adhere to the following style guidelines to ensure
consistency across the project.

### 4.1. PHP Code Standards

PHP code must follow these standards.

-   **Brace Style:** Use Allman brace style where opening braces are
    placed on a new line.

    ```php
    function exampleFunction()
    {
        // Code here
    }

    if ($condition)
    {
        // Code here
    }
    ```

-   **Indentation:** Use four spaces for indentation. Do not use tabs.

-   **Naming Conventions:**
    -   Class names use PascalCase: `UserManagement`, `OrderProcessor`.
    -   Methods and variables use camelCase: `getUserById`, `$orderTotal`.
    -   Constants use UPPER_SNAKE_CASE: `ORDER_STATUS_PENDING`.

-   **Docblocks:** All functions must include docblocks that describe
    the purpose, parameters, and return values.

    ```php
    /**
     * Authenticates a user with email/username and password.
     *
     * @param string $identifier User's email or username.
     * @param string $password User's password.
     * @param string $csrfToken CSRF token for validation.
     * @return array Associative array with 'success' (bool)
     *               and 'message' (string).
     */
    function authenticateUser($identifier, $password, $csrfToken = '')
    ```

### 4.2. JavaScript Code Standards

JavaScript code must follow these standards.

-   **Indentation:** Use two spaces for indentation. Do not use tabs.
-   **Semicolons:** Terminate all statements with semicolons.
-   **Variable Declaration:** Use `const` for values that do not change
    and `let` for values that change. Do not use `var`.
-   **Naming Conventions:** Use camelCase for variable and function
    names.
-   **Event Listeners:** Use `addEventListener` instead of inline
    event handlers.

### 4.3. CSS Code Standards

CSS code must follow these standards.

-   **External Files:** All CSS must reside in external files only. No
    inline style attributes are permitted.
-   **Naming Conventions:** Class names use kebab-case:
    `.admin-sidebar`, `.order-card-body`.
-   **Variables:** Use CSS custom properties (variables) for colours
    and spacing to maintain consistency.
-   **Selector Specificity:** Avoid over-qualifying selectors. Keep
    specificity low and predictable.

### 4.4. HTML Code Standards

HTML code must follow these standards.

-   **Semantic Elements:** Use semantic HTML5 elements such as
    `<header>`, `<nav>`, `<main>`, `<section>`, and `<footer>`.
-   **Accessibility:** Include `alt` attributes for all images.
    Provide proper `aria` attributes for interactive elements.
-   **Form Validation:** Validate forms on both client and server
    sides. Do not rely on client-side validation alone.

---

## 5. Security Requirements

Security is a critical aspect of the Campus Eats system. All
contributions must adhere to the following security requirements.

### 5.1. SQL Injection Prevention

All database queries must use PDO prepared statements through the
`DatabaseConnection` class. Never concatenate user input into SQL
strings.

```php
// Correct: Use prepared statements.
$user = $db->fetchOne(
    "SELECT * FROM users WHERE email = :email",
    array('email' => $email)
);

// Incorrect: Never concatenate user input.
$user = $db->fetchOne("SELECT * FROM users WHERE email = '$email'");
```

### 5.2. Cross-Site Scripting Prevention

All output to HTML must be escaped using `htmlspecialchars()` with the
`ENT_QUOTES` flag. Use the `escapeOutput()` helper function for this
purpose.

```php
echo escapeOutput($userInput);
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

### 5.3. Cross-Site Request Forgery Protection

All forms that modify data must include a CSRF token generated by
`generateCsrfToken()` and validated by `validateCsrfToken()`.

```php
// In the form.
<input type="hidden" name="csrf_token"
       value="<?php echo generateCsrfToken(); ?>">

// In the processing script.
if (!validateCsrfToken($_POST['csrf_token'] ?? ''))
{
    // Reject the request.
}
```

### 5.4. Password Security

Passwords must be hashed using `password_hash()` with bcrypt and a cost
factor of 12. Never store plain text passwords.

```php
$hashedPassword = password_hash($password, PASSWORD_DEFAULT,
                                array('cost' => 12));
```

### 5.5. Session Security

Sessions must be started using the `startSecureSession()` function.
This function configures secure session parameters including HttpOnly,
SameSite, and a custom session name.

---

## 6. Commit Message Format

Template:

```
Header line: Explain the commit in one line (use the imperative)

Body of commit message is a few lines of text, explaining things
in more detail, possibly giving some background about the issue
being fixed, etc.

The body of the commit message can be several paragraphs, and
please do proper word-wrap and keep columns shorter than about
74 characters or so. That way "git log" will show things
nicely even when it's indented.

Make sure you explain your solution and why you're doing, what you're
doing, as opposed to describing what you're doing. Reviewers and your
future self can read the patch, but might not understand why a
particular solution was implemented.

Reported-by: whoever-reported-it
Signed-off-by: Your Name
```

---

## 7. Testing Requirements

All contributions must be tested thoroughly before submission.

### 7.1. Local Testing

Set up the Campus Eats system locally using the installation
instructions in the main documentation. Test your changes in a local
environment before submitting a pull request.

### 7.2. Functional Testing

Ensure that all user flows work as expected. Test the following
scenarios.

-   User registration and login.
-   Vendor menu management.
-   Order placement and tracking.
-   Admin user and vendor management.

### 7.3. Security Testing

Verify that security controls are working correctly.

-   Test that SQL injection attempts are blocked.
-   Test that CSRF tokens are validated.
-   Test that XSS attempts are escaped.
-   Test that rate limiting is enforced.

### 7.4. Cross-Browser Testing

Test your changes in the following modern browsers.

-   Google Chrome (latest version)
-   Mozilla Firefox (latest version)
-   Apple Safari (latest version)
-   Microsoft Edge (latest version)

### 7.5. Mobile Testing

Test your changes on the following screen sizes.

-   Mobile (320px to 480px)
-   Tablet (768px to 1024px)
-   Desktop (1025px and above)

---

## 8. Documentation Standards

All contributions must include appropriate documentation.

### 8.1. Code Documentation

-   **File Headers:** All PHP files must include a header comment that
    describes the file's purpose, version history, and any
    corrections.

-   **Function Docblocks:** All functions must include docblocks that
    describe the purpose, parameters, and return values.

-   **Complex Logic:** Add inline comments to explain complex or non-
    obvious logic.

### 8.2. Visual Media Guidelines

All visual media, including screenshots and images of the application,
must be stored in a dedicated folder within the project directory. This
folder should be clearly structured and named accordingly to indicate
that it contains all visual content related to the application.

-   **Folder Name:** Use a descriptive name such as `images`,
    `screenshots`, or `media`.
-   **Referencing Images:** When referencing images in documentation,
    use relative paths to the dedicated folder. Do not embed images
    directly in documentation files.
-   **File Formats:** Use standard image formats such as PNG, JPEG, or
    SVG for screenshots and diagrams.

### 8.3. Updating This Document

When contributing changes that affect the contribution process itself,
update this document. Include the changes in your pull request.

### 8.4. Compliance with Process Document

All development must treat the process document as an integrated whole,
not as an isolated component. All design decisions, feature additions,
and code changes must be validated against the document to ensure
compliance with assessment criteria.

---

*END OF DOCUMENT*

---