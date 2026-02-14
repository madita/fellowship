# Fellowship Codebase Audit Report
**Date:** 2026-02-11  
**Branch:** cleanup/audit-fixes  
**Auditor:** OpenClaw AI

## Executive Summary
This audit identified security issues, code quality concerns, and technical debt in the Fellowship codebase. Critical issues require immediate attention before production deployment.

---

## 🔴 CRITICAL Issues

### 1. Missing Authorization in Conversation Controller
**File:** `app/Http/Controllers/Conversation/ConversationController.php`  
**Line:** 29-31  
**Severity:** CRITICAL  
**Impact:** Users can view conversations they're not participants of

**Issue:**
```php
public function show(Conversation $conversation, Request $request): JsonResponse
{
    // TODO: Add authorization
    // $this->authorize('show', $conversation);
```

**Risk:** Any authenticated user can access any conversation by guessing/enumerating conversation IDs. This exposes private messages.

**Fix Required:**
- Implement authorization check to verify user is a participant
- Create a policy or inline check before loading conversation data
- Consider using route model binding with scoped queries

**Recommendation:**
```php
public function show(Conversation $conversation, Request $request): JsonResponse
{
    // Verify user is a participant
    if (!$conversation->users->contains(auth()->id())) {
        abort(403, 'You are not authorized to view this conversation.');
    }
    
    // ... rest of method
}
```

---

## 🟡 MEDIUM Issues

### 2. Raw Database Queries (Potential SQL Injection)
**File:** `app/Http/Controllers/EventController.php`  
**Lines:** Multiple  
**Severity:** MEDIUM  

**Issue:**
```php
$isGoing = DB::table('event_guests')
    ->where('event_id', '=', $event->id)
    ->where('user_id', '=', $user->id)
    ->first();
```

**Risk:** While these queries use parameter binding (safe), they bypass Eloquent relationships, making the code harder to maintain.

**Recommendation:** Use Eloquent relationships instead of raw DB queries.

---

### 3. Case-Insensitive Username Search Vulnerability
**File:** `app/Http/Controllers/UserController.php`  

**Issue:**
```php
->where(DB::raw('LOWER(username)'), 'LIKE', '%'.Str::lower($q).'%')
```

**Risk:** Using `DB::raw()` with string concatenation can be risky. While `Str::lower()` sanitizes input here, it's better to use proper query builders.

**Recommendation:**
```php
->whereRaw('LOWER(username) LIKE ?', ['%' . Str::lower($q) . '%'])
```

---

### 4. Placeholder API Endpoints
**Files:**
- `resources/js/components/landing/widgets/ContactFormWidget.vue`
- `resources/js/components/landing/widgets/NewsletterWidget.vue`

**Issue:** Contact form and newsletter widgets have TODO comments for API endpoints.

**Risk:** These features may not be functional in production.

**Recommendation:** Implement backend endpoints or remove these features if not needed.

---

### 5. Category Update Issue
**File:** `resources/js/pages/admin/PageForm.vue`  

**Issue:**
```javascript
//TODO categorie update needs fixing can only delete all values
```

**Risk:** Category management is broken, potentially causing data loss.

**Recommendation:** Debug and fix category update logic before production use.

---

## 🟢 LOW / Code Quality Issues

### 6. Missing Composer/NPM Dependencies
**Status:** All node_modules are missing (expected in dev)  
**Action Required:** Run `npm install` before testing

---

### 7. Outdated Dependencies (Potential)
**Major Version Upgrades Available:**
- `@tiptap/*` packages: v2.27 → v3.19 (major version)
- `laravel-echo`: v1.19 → v2.3 (major version)
- `@googlemaps/js-api-loader`: v1.16 → v2.0 (major version)
- `echarts`: v5.6 → v6.0 (major version)

**Recommendation:** 
- Test major upgrades in a separate branch
- Review breaking changes for each package
- Tiptap v3 likely has significant API changes

---

### 8. Laravel 12 + PHP 8.1 Compatibility
**Current Stack:** Laravel 12 + PHP ^8.1  
**Note:** Laravel 12 requires PHP 8.2+ officially  

**Recommendation:** Update `composer.json` to require PHP ^8.2 or verify Laravel 12 compatibility with 8.1

---

## 📋 Configuration Review

### .env.example
✅ No hardcoded secrets  
✅ Proper placeholder values  
⚠️ Default `APP_DEBUG=true` (should be `false` in production docs)  
⚠️ Default `DB_PASSWORD=` empty (document security requirements)

---

## Security Best Practices Checklist

| Check | Status | Notes |
|-------|--------|-------|
| No hardcoded credentials | ✅ | Clean |
| Authorization on routes | ⚠️ | Missing in ConversationController |
| CSRF protection | ✅ | Laravel default |
| SQL injection prevention | ⚠️ | Mostly safe, some DB::raw usage |
| Mass assignment protection | ✅ | Using fillable/guarded |
| Debug mode off in production | ⚠️ | Document in deployment guide |
| Error logging configured | ✅ | Using Laravel stack |

---

## Recommended Action Plan

### Phase 1: Critical Fixes (Do First)
1. ✅ Fix conversation authorization vulnerability
2. ✅ Test conversation access control
3. ✅ Add integration tests for auth checks

### Phase 2: Medium Priority
1. Refactor EventController to use Eloquent relationships
2. Fix category update bug in PageForm.vue
3. Implement or remove placeholder API endpoints

### Phase 3: Maintenance
1. Review and test major dependency upgrades (separate branch)
2. Update PHP requirement to 8.2 if needed
3. Add automated security scanning (e.g., `composer audit`)

### Phase 4: Documentation
1. Add security guidelines to README
2. Document deployment checklist (debug mode, secrets, etc.)
3. Create CONTRIBUTING.md with code standards

---

## Code Quality Observations

### Strengths
- ✅ Well-structured Laravel application
- ✅ Modern Vue 3 + Vite setup
- ✅ Real-time features with Laravel Echo
- ✅ Comprehensive feature set (auth, permissions, wiki, events)
- ✅ Good use of Laravel conventions

### Areas for Improvement
- Mixed use of Eloquent vs raw queries
- Some incomplete features (TODOs in production code)
- Need for more automated testing coverage
- Missing API documentation

---

## Testing Recommendations

### Missing Test Coverage
- Conversation authorization tests
- Event relationship tests
- Category update flow tests

### Suggested Tests
```php
// tests/Feature/ConversationAuthorizationTest.php
public function test_user_cannot_view_conversation_they_are_not_part_of()
{
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    $conversation = Conversation::factory()->create();
    $conversation->users()->attach($otherUser);
    
    $this->actingAs($user)
        ->getJson("/api/conversations/{$conversation->id}")
        ->assertForbidden();
}
```

---

## Conclusion

The codebase is **generally well-structured** but has **one critical security flaw** that must be fixed before production deployment. Medium-priority issues should be addressed in the near term, and technical debt items can be scheduled for future sprints.

**Overall Grade:** B- (would be A- after critical fix)

**Ready for Production:** ❌ Not until conversation authorization is fixed  
**Ready for Development:** ✅ Yes, with caution

---

## Next Steps

1. Review and approve this audit report
2. Implement critical fixes (conversation authorization)
3. Test fixes thoroughly
4. Create GitHub issues for medium/low priority items
5. Update documentation with security guidelines
