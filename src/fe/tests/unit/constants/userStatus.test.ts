import { describe, expect, it } from 'vitest'
import { USER_STATUS_OPTIONS } from '../../../constants/userStatus'

/**
 * Phase 11 (first real frontend test in this repo -- see
 * docs/TESTING_STRATEGY.md). docs/adr/0002-status-option-list-shared-constant.md
 * explicitly documents there is "no runtime link" between this file and
 * the backend's App\Enums\UserStatus -- it must be updated by hand,
 * value-for-value, whenever the backend enum changes. This test encodes
 * that contract as an explicit, named value set (the values/order read
 * directly from src/be/app/Enums/UserStatus.php at the time this test was
 * written) so a silent drift -- someone editing one side and forgetting
 * the other -- fails a test instead of only being caught by chance in
 * manual QA.
 */
describe('USER_STATUS_OPTIONS', () => {
  it('mirrors the backend UserStatus enum value-for-value, in the same order', () => {
    const values = USER_STATUS_OPTIONS.map((option) => option.value)

    expect(values).toEqual([
      'new_register',
      'it_works',
      'vacation',
      'maternity_leave',
      'education',
      'business_trip',
      'out',
      'removed',
    ])
  })

  it('gives every option a non-empty, human-readable title', () => {
    for (const option of USER_STATUS_OPTIONS) {
      expect(typeof option.title).toBe('string')
      expect(option.title.length).toBeGreaterThan(0)
    }
  })

  it('has no duplicate values', () => {
    const values = USER_STATUS_OPTIONS.map((option) => option.value)
    expect(new Set(values).size).toBe(values.length)
  })
})
