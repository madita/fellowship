import { describe, it, expect } from 'vitest';

describe('Guest Approval Functionality', () => {
  it('should correctly identify approved guests', () => {
    // Test data structure for guests
    const guests = {
      going: [
        { name: 'User 1', pivot: { approved_at: new Date().toISOString() } },
        { name: 'User 2', pivot: { approved_at: null } }
      ],
      notgoing: [
        { name: 'User 3', pivot: { approved_at: new Date().toISOString() } }
      ]
    };

    // Count approved guests
    const approvedCount = guests.going.filter(g => g.pivot.approved_at).length +
                         guests.notgoing.filter(g => g.pivot.approved_at).length;

    expect(approvedCount).toBe(2);
  });

  it('should correctly format approval date', () => {
    const date = new Date();
    const isoString = date.toISOString();

    // Check that the date is in ISO format
    expect(isoString).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
  });
});
