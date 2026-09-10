<?php
// helpers/PermissionHelper.php
// User-level permission checks on top of SettingsHelper's role/permission
// storage, plus the runtime rules for how "Can Manage Reports" behaves
// differently depending on a staff user's `user_type`:
//
//   user_type = 'barangay_personnel' -> can manage reports only from
//       their own assigned_barangay (verify / escalate to MENRO /
//       mark resolved / reject) — reports NOT yet escalated.
//   user_type = 'menro_staff' or 'admin' -> can manage ESCALATED reports
//       from any barangay (mark resolved / reject escalation).
//
// Include this after SettingsHelper.php:
//   require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';
//   require_once dirname(__DIR__) . '/helpers/PermissionHelper.php';

class PermissionHelper {

    /**
     * Does the current logged-in user (or a given user array) have the
     * given permission? Handles the super-admin bypass.
     *
     * @param string $permissionKey e.g. 'can_manage_reports'
     * @param array|null $user Optional user row (needs role, role_id,
     *        user_type, id). Defaults to $_SESSION.
     * @return bool
     */
    public static function userHasPermission($permissionKey, $user = null) {
        $user = $user ?? self::sessionUser();
        if (!$user) {
            return false;
        }

        // Primary super-admin: unrestricted, matches the existing
        // "the primary super-admin account is always unrestricted" rule
        // from the Permissions tab.
        if (($user['user_type'] ?? null) === 'admin') {
            return true;
        }

        if (empty($user['role_id'])) {
            return false;
        }

        return SettingsHelper::hasPermission((int)$user['role_id'], $permissionKey);
    }

    /**
     * OR-semantics version of userHasPermission(): returns true when the
     * current user holds ANY of the given keys. If $keys is empty the
     * result is false so an empty grant set never unlocks anything.
     *
     * @param string[] $keys
     * @param array|null $user Optional user row, defaults to session.
     * @return bool
     */
    public static function userHasAnyPermission(array $keys, $user = null) {
        foreach ($keys as $key) {
            if (self::userHasPermission($key, $user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Route -> permission map used by the front router (index.php) and the
     * sidebar. A page is only reachable/visible when the user's role holds
     * at least one of the listed keys (AND the legacy role route exists).
     *
     * Pages NOT listed here are intentionally open to every logged-in user
     * (profile, announcements, notifications, citizen flows, etc.).
     *
     * @return array<string,string[]>
     */
    private static function pagePermissionMap() {
        return [
            // Dashboards contain both the hazard map and the analytics.
            'dashboard'                     => ['can_view_analytics', 'can_view_map'],
            'dashboard-report'              => ['can_view_analytics', 'can_view_map'],
            'barangay-dashboard-report'     => ['can_view_analytics', 'can_view_map'],
            'barangay-dashboard-print'      => ['can_view_analytics', 'can_view_map'],

            // Reports (view vs manage).
            'all-reports'                   => ['can_view_reports'],
            'all-reports-print'             => ['can_view_reports'],
            'verify-reports'                => ['can_manage_reports'],
            'barangay-manage-reports-print' => ['can_manage_reports'],
            'reporters-directory'           => ['can_view_reports'],
            'barangay-reporters-print'      => ['can_view_reports'],

            // Account / staff management.
            'manage-users'                  => ['can_manage_users', 'can_manage_staff'],
            'users-report'                  => ['can_manage_users', 'can_manage_staff'],

            // System-level tools.
            'audit-logs'                    => ['can_manage_system'],
            'audit-logs-report'             => ['can_manage_system'],
        ];
    }

    /**
     * The permission keys required to access a given route (empty when the
     * route is not permission-gated).
     * @return string[]
     */
    public static function pagePermissions($page) {
        $map = self::pagePermissionMap();
        return $map[$page] ?? [];
    }

    /**
     * Enforce page-level access for the current user. Redirects to a safe
     * landing page (their permitted home, or the always-open profile page)
     * when the user does not hold a required permission for $page.
     */
    public static function requirePagePermission($page) {
        $needed = self::pagePermissions($page);
        if (!$needed) {
            return; // Open to every logged-in user.
        }
        if (self::userHasAnyPermission($needed)) {
            return;
        }

        // Landing on the dashboard after login is a normal case for roles
        // without dashboard rights; a silent bounce beats a scary error.
        if ($page !== 'dashboard') {
            $_SESSION['error'] = "You do not have permission to access that page.";
        }

        header("Location: " . BASE_URL . "index.php?page=" . self::fallbackPage());
        exit();
    }

    /**
     * Best landing page for a user after a permission-denied redirect:
     * their own dashboard when permitted, otherwise the first route their
     * legacy role can open, otherwise the always-accessible profile page.
     *
     * @param array|null $user Optional user row, defaults to session.
     * @return string
     */
    public static function fallbackPage($user = null) {
        $user   = $user ?? self::sessionUser();
        $role   = $user['role'] ?? null;

        if (self::userHasAnyPermission(['can_view_analytics', 'can_view_map'], $user)) {
            return 'dashboard';
        }

        $candidates = ($role === 'barangay_official')
            ? ['verify-reports', 'reporters-directory', 'barangay-dashboard-report']
            : ['all-reports', 'manage-users', 'settings'];

        foreach ($candidates as $candidate) {
            // 'settings' is gated earlier in index.php by can_manage_system,
            // so it must be checked against that key, not the route map.
            $keys = ($candidate === 'settings')
                ? ['can_manage_system']
                : self::pagePermissions($candidate);
            if (self::userHasAnyPermission($keys, $user)) {
                return $candidate;
            }
        }

        return 'profile';
    }

    /**
     * Pull the "current user" shape PermissionHelper needs out of the
     * session. Assumes AuthController stores these on login (see note
     * at the bottom of this file for the two lines to add there).
     */
    private static function sessionUser() {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'        => $_SESSION['user_id'],
            'role'      => $_SESSION['user_role'] ?? null,
            'role_id'   => $_SESSION['role_id'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? null,
            'barangay_id' => $_SESSION['barangay_id'] ?? null,
        ];
    }

    /**
     * Can the current user act on this specific report right now?
     * This is the runtime rule described in the spec: barangay
     * personnel manage their own barangay's non-escalated reports;
     * MENRO staff / admin manage escalated reports from any barangay.
     * Both still require the 'can_manage_reports' permission to be on
     * for their role.
     *
     * @param array $report Must include 'barangay_id' and 'status'
     *        (and ideally 'escalated' / 'escalated_to_menro').
     * @param array|null $user Optional user row, defaults to session.
     * @return bool
     */
    public static function canManageReport(array $report, $user = null) {
        $user = $user ?? self::sessionUser();
        if (!$user) {
            return false;
        }

        // Super-admin bypass.
        if (($user['user_type'] ?? null) === 'admin') {
            return true;
        }

        if (!self::userHasPermission('can_manage_reports', $user)) {
            return false;
        }

        $isEscalated = !empty($report['escalated']) || !empty($report['escalated_to_menro'])
            || in_array($report['status'] ?? '', ['escalated', 'escalated_pending'], true);

        if (($user['user_type'] ?? null) === 'barangay_personnel') {
            // Own barangay only, and only before it's escalated to MENRO.
            return !$isEscalated
                && !empty($user['barangay_id'])
                && (int)$user['barangay_id'] === (int)($report['barangay_id'] ?? 0);
        }

        if (($user['user_type'] ?? null) === 'menro_staff') {
            // Escalated reports only, from any barangay.
            return $isEscalated;
        }

        return false;
    }

    /**
     * The set of actions a user is allowed to take on a report right
     * now, for building the action buttons in verify_reports.php /
     * all_reports.php. Returns [] if canManageReport() is false.
     * @return string[]
     */
    public static function allowedReportActions(array $report, $user = null) {
        $user = $user ?? self::sessionUser();
        if (!self::canManageReport($report, $user)) {
            return [];
        }

        if (($user['user_type'] ?? null) === 'admin') {
            // Unrestricted: full action set regardless of escalation state.
            $isEscalated = !empty($report['escalated']) || !empty($report['escalated_to_menro'])
                || in_array($report['status'] ?? '', ['escalated', 'escalated_pending'], true);
            return $isEscalated
                ? ['mark_resolved', 'reject_escalation']
                : ['verify', 'escalate', 'mark_resolved', 'reject'];
        }

        if (($user['user_type'] ?? null) === 'barangay_personnel') {
            return ['verify', 'escalate', 'mark_resolved', 'reject'];
        }

        if (($user['user_type'] ?? null) === 'menro_staff') {
            return ['mark_resolved', 'reject_escalation'];
        }

        return [];
    }
}

// ============================================================
// INTEGRATION NOTES (files not available to edit directly)
// ============================================================
// 1) AuthController.php login block — alongside the existing
//        $_SESSION['user_role'] = $row['role'];
//        $_SESSION['barangay_id'] = $row['barangay_id'];
//    add:
//        $_SESSION['role_id']   = $row['role_id'];
//        $_SESSION['user_type'] = $row['user_type'];
//    (in both the standard-login block and the needsPasswordReset
//    block, so PermissionHelper works immediately after first login too)
//
// 2) verify_reports.php / all_reports.php — gate the action buttons with:
//        require_once dirname(__DIR__, 2) . '/helpers/PermissionHelper.php';
//        $actions = PermissionHelper::allowedReportActions($report);
//    and re-check server-side before applying any status change:
//        if (!PermissionHelper::canManageReport($report)) {
//            $_SESSION['error'] = "You are not permitted to manage this report.";
//            header("Location: ..."); exit();
//        }
//
// 3) Settings pages / categories / announcements / user deletion / PDF
//    export use the feature-level keys: 'can_manage_system',
//    'can_manage_users', 'can_manage_staff', 'can_export_reports'.
//    Legacy keys ('can_edit_settings', 'can_manage_categories',
//    'can_broadcast_announcements', 'can_delete_users') are resolved to
//    their new equivalents by SettingsHelper::resolvePermissionKey().
?>