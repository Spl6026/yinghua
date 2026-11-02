// 分頁列表的讀取核心，後台表格與公開頁的景點、電影、行程選點共用：
// - 只採用最後一次請求的結果，較慢的舊回應不會蓋掉較新的畫面；
// - 讀取成功才更新頁碼，換頁失敗時停留在原本那一頁；
// - 目前頁碼超過總頁數（例如刪掉最後一頁唯一一筆）時，自動改讀最後一頁。

export function createPagedList({ fetchPage, render, onError, onBusy = () => {}, pageSize = 20, total = 0 }) {
  const state = { page: 1, total, pageSize, busy: false };
  let generation = 0;
  const lastPage = () => Math.max(1, Math.ceil(state.total / state.pageSize));
  async function load(page = state.page) {
    const request = ++generation;
    state.busy = true;
    onBusy(state);
    try {
      const result = await fetchPage({ page, pageSize: state.pageSize });
      if (request !== generation) return;
      state.total = result.total;
      state.pageSize = result.pageSize || state.pageSize;
      if (page > lastPage()) {
        await load(lastPage());
        return;
      }
      state.page = page;
      render(result, state);
    } catch (error) {
      if (request === generation) onError(error, state);
    } finally {
      if (request === generation) {
        state.busy = false;
        onBusy(state);
      }
    }
  }
  return {
    state,
    load,
    // 篩選條件改變時回到第一頁。
    reset: () => load(1),
    goTo(page) {
      if (state.busy || page < 1 || page > lastPage()) return;
      return load(page);
    },
    hasPrevious: () => state.page > 1,
    hasNext: () => state.page * state.pageSize < state.total,
  };
}
