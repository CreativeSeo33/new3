import { reactive, computed } from 'vue'

type LoadingState = {
  inFlightCount: number
  visible: boolean
  showTimerId: number | null
  thresholdMs: number
}

const state = reactive<LoadingState>({
  inFlightCount: 0,
  visible: false,
  showTimerId: null,
  // Анти-мерцание: поставить 120–180 для задержки показа. 0 — выключено.
  thresholdMs: 0,
})

function startGlobalLoading() {
  state.inFlightCount += 1

  if (state.visible) return

  if (state.thresholdMs > 0) {
    if (state.showTimerId != null) return
    state.showTimerId = window.setTimeout(() => {
      state.showTimerId = null
      if (state.inFlightCount > 0) state.visible = true
    }, state.thresholdMs)
  } else {
    state.visible = true
  }
}

function stopGlobalLoading() {
  // Если ещё не показали (ждём таймер), и это последний запрос — отменим показ
  if (state.thresholdMs > 0 && state.showTimerId != null && state.inFlightCount <= 1) {
    clearTimeout(state.showTimerId)
    state.showTimerId = null
  }

  if (state.inFlightCount > 0) state.inFlightCount -= 1

  if (state.inFlightCount === 0) {
    state.visible = false
  }
}

function resetGlobalLoading() {
  if (state.showTimerId != null) {
    clearTimeout(state.showTimerId)
    state.showTimerId = null
  }
  state.inFlightCount = 0
  state.visible = false
}

export const isGlobalLoading = computed<boolean>(() => state.visible)

export const uiLoading = {
  state,
  startGlobalLoading,
  stopGlobalLoading,
  resetGlobalLoading,
  isGlobalLoading,
}


