# Universal Code Style Rules
Version: 1.0

Audience: Humans & AI Code Assistants  
Scope: Language-agnostic (C-like syntax used for examples)

---

## 1. Purpose

This document defines **mandatory programming rules** that must be followed by humans and AI-generated code.

Primary goals:

- Predictable structure
- Readability over brevity
- Explicit control flow
- Defensive programming
- Long-term maintainability

Automation must never sacrifice clarity.

---

## 2. Core Principles

### 2.1 Readability Over Cleverness

- Avoid one-liners
- Avoid implicit behavior
- Avoid compressed logic

Code must be easy to scan vertically.

---

### 2.2 Explicit Over Implicit

- All blocks must be explicit
- All scopes must be visible
- All control flow must be obvious

Hidden logic is forbidden.

---

### 2.3 Fail Fast / Fail First

- Validate input immediately
- Abort execution early
- Never propagate invalid state

---

### 2.4 Early Return / Else-less Code

- Prefer guard clauses
- Avoid else when possible
- Keep happy-path linear

---

## 3. Control Flow Rules

### 3.1 Mandatory Braces

All control structures must use braces.

❌ Wrong
```js
if (true) return;
```

✅ Correct
```js
if (true) {
    return;
}
```

---

### 3.2 No One-Line Control Statements

Applies to:

- if / else
- for / while / foreach
- try / catch

❌ Wrong
```js
if (user) process(user);
```

✅ Correct
```js
if (user) {
    process(user);
}
```

---

### 3.3 Else-less Pattern

❌ Wrong
```js
if (!user) {
    return null;
} else {
    return user.name;
}
```

✅ Correct
```js
if (!user) {
    return null;
}

return user.name;
```

---

## 4. Fail Fast & Guard Clauses

### 4.1 Validate Early

❌ Wrong
```js
function save(data) {
    process(data);

    if (!data) {
        return false;
    }
}
```

✅ Correct
```js
function save(data) {
    if (!data) {
        return false;
    }

    process(data);

    return true;
}
```

---

### 4.2 Avoid Deep Nesting

❌ Wrong
```js
if (a) {
    if (b) {
        if (c) {
            run();
        }
    }
}
```

✅ Correct
```js
if (!a) {
    return;
}

if (!b) {
    return;
}

if (!c) {
    return;
}

run();
```

---

## 5. Variable Declaration Rules

### 5.1 Block Scope Only

Avoid function-scoped or ambiguous variables.

❌ Wrong
```js
var foo = 'value';
```

✅ Correct
```js
let foo = 'value';
```

---

### 5.2 No Function-Scoped Variables

❌ Wrong
```js
function example() {
    var bar = 'value';
}
```

✅ Correct
```js
function example() {
    let bar = 'value';
}
```

---

## 6. Loop Rules

### 6.1 Loop Counters Must Be Block-Scoped

❌ Wrong
```js
for (var i = 0; i < 10; i++) {
    log(i);
}
```

✅ Correct
```js
for (let i = 0; i < 10; i++) {
    log(i);
}
```

---

### 6.2 Iteration Variables Must Be Block-Scoped

❌ Wrong
```js
for (var key in object) {
    log(key);
}
```

✅ Correct
```js
for (let key in object) {
    log(key);
}
```

---

## 7. Vertical Formatting Rules

### 7.1 Blank Line Between Declarations and Control Flow

❌ Wrong
```js
let key = 'value';
if (true) {
    doSomething();
}
```

✅ Correct
```js
let key = 'value';

if (true) {
    doSomething();
}
```

---

### 7.2 One Concept per Block

- Declarations define state
- Conditions control flow
- Execution performs actions

Separate concepts with blank lines.

---

## 8. Naming Rules

### 8.1 Names Must Describe Intent

❌ Wrong
```js
const d = getData();
```

✅ Correct
```js
const userProfile = fetchUserProfile();
```

---

### 8.2 Boolean Names Must Read as Questions

❌ Wrong
```js
if (user.active) {}
```

✅ Correct
```js
if (user.isActive) {}
```

---

## 9. Functions

### 9.1 Single Responsibility

- One function, one reason to change
- No god-functions

---

### 9.2 Prefer Small, Composable Functions

❌ Wrong
```js
function handle(req) {
    validate(req);
    auth(req);
    process(req);
    respond(req);
}
```

✅ Correct
```js
function handle(req) {
    if (!isValid(req)) {
        return error();
    }

    if (!isAuthorized(req)) {
        return forbidden();
    }

    return process(req);
}
```

---

## 10. Comments

### 10.1 Code Explains How, Comments Explain Why

❌ Wrong
```js
// increment counter
i++;
```

✅ Correct
```js
// Early return avoids unnecessary database calls
if (!user.isActive) {
    return;
}
```

---

## 11. Error Handling

### 11.1 Never Ignore Errors

❌ Wrong
```js
try {
    run();
} catch (e) {}
```

✅ Correct
```js
try {
    run();
} catch (error) {
    logError(error);
    throw error;
}
```

---

## 12. Forbidden Patterns

The following are forbidden even if supported by the language:

- One-line control flow
- Function-scoped variables where block scope exists
- Missing blank lines between logical sections
- Deep nesting instead of guard clauses
- Dense vertical code

---

## 13. AI Enforcement Rules

When generating code, the AI must:

1. Follow all rules in this document
2. Prefer clarity over brevity
3. Always use explicit blocks
4. Use early returns and guard clauses
5. Apply block scope by default
6. Insert blank lines between logical sections

If there is doubt, choose the more explicit solution.

---

## 14. Guiding Mental Model

Code should read top-to-bottom as:

1. Validate
2. Guard
3. Prepare
4. Execute
5. Return

Anything that breaks this flow must be rewritten.

---

End of document.
