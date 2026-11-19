// Single source of truth for the `company_users.status` dropdown options
// used by both the create and edit pages.
//
// Mirrors the backend's App\Enums\UserStatus (src/be/app/Enums/UserStatus.php)
// value-for-value. There is no runtime link between the two (no API
// endpoint exposes this list) — if a status is added, removed, or
// relabeled on the backend enum, update this file to match in the same
// change (see docs/adr/0002-status-option-list-shared-constant.md).
export const USER_STATUS_OPTIONS = [
  { title: 'Yeni Qeydiyyat', value: 'new_register' },
  { title: 'İşləyir', value: 'it_works' },
  { title: 'Məzuniyyət', value: 'vacation' },
  { title: 'Analıq məzuniyyəti', value: 'maternity_leave' },
  { title: 'Təhsil', value: 'education' },
  { title: 'Ezamiyyət', value: 'business_trip' },
  { title: 'Çıxıb', value: 'out' },
  { title: 'Çıxarılıb', value: 'removed' },
]
