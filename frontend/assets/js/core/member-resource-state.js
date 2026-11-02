// [E-27] 收藏狀態協調器：API／身分為明確依賴，可直接測試逾時、競態與換帳號。
export class MemberResourceState {
  constructor(api, readIdentity) {
    this.api = api;
    this.readIdentity = readIdentity;
    this.member = undefined;
    this.statuses = new Map();
    this.busy = new Set();
    this.generation = 0;
    this.ids = [];
  }

  async refresh(ids = this.ids) {
    const request = ++this.generation;
    this.ids = [...new Set(ids)];
    const requested = this.ids;
    try {
      const member = await this.readIdentity();
      const statuses = new Map();
      if (member) {
        for (let offset = 0; offset < requested.length; offset += 100) {
          const chunk = requested.slice(offset, offset + 100);
          const result = await this.api.favoriteStatus(chunk);
          for (const id of chunk) {
            const value = result.statuses.find(row => row.attractionId === id)?.favorited;
            if (typeof value !== 'boolean') throw new Error('無法讀取完整收藏狀態，請重新讀取。');
            statuses.set(id, value);
          }
        }
      } else requested.forEach(id => statuses.set(id, false));
      if (request !== this.generation) return false;
      this.member = member;
      this.statuses = statuses;
      return true;
    } catch (error) {
      if (request !== this.generation) return false;
      this.member = undefined;
      this.statuses.clear();
      throw error;
    }
  }

  async favorite(id) {
    if (this.busy.has(id)) return { busy: true };
    if (this.member === null) return { login: true };
    if (!this.member || !this.statuses.has(id)) return { retry: true };
    const memberId = this.member.id;
    const saved = !this.statuses.get(id);
    this.busy.add(id);
    ++this.generation;
    try {
      await this.api.saveFavorite(id, saved);
      const refreshed = await this.refresh();
      return { changed: refreshed && this.member?.id === memberId, saved };
    } catch (error) {
      try {
        const refreshed = await this.refresh();
        if (
          refreshed &&
          error.status === 0 &&
          this.member?.id === memberId &&
          this.statuses.get(id) === saved
        )
          return { changed: true, saved, reconciled: true };
      } catch {
        /* 狀態維持未知；不能改成確定未收藏。 */
      }
      throw error;
    } finally {
      this.busy.delete(id);
    }
  }
}
