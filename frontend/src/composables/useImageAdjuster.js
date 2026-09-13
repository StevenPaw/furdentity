import { computed, onBeforeUnmount, nextTick, ref, watch } from 'vue'

// Shared zoom/pan math for live-adjusting an image directly inside its real
// destination element (the profile card's background strip or avatar
// circle), rather than a standalone crop-stage proxy. `stage` is that real
// element (used only to measure width/height); `image` is the <img> actually
// being panned/zoomed within it. Output aspect ratio is read straight off
// the stage's own rendered size, so it always matches whatever the card face
// is doing at that viewport width instead of a hardcoded constant.
export function useImageAdjuster() {
  const stage = ref(null)
  const image = ref(null)

  const naturalWidth = ref(0)
  const naturalHeight = ref(0)
  const stageWidth = ref(0)
  const stageHeight = ref(0)
  const zoom = ref(1)
  const offsetX = ref(0)
  const offsetY = ref(0)

  // Scale at which the image exactly covers the stage - the floor for zoom,
  // so the user can never drag the image smaller than the frame.
  const baseScale = computed(() => {
    if (!naturalWidth.value || !stageWidth.value) return 1
    return Math.max(stageWidth.value / naturalWidth.value, stageHeight.value / naturalHeight.value)
  })
  const effectiveScale = computed(() => baseScale.value * zoom.value)
  const displayWidth = computed(() => naturalWidth.value * effectiveScale.value)
  const displayHeight = computed(() => naturalHeight.value * effectiveScale.value)

  function measureStage() {
    if (!stage.value) return
    stageWidth.value = stage.value.clientWidth
    stageHeight.value = stage.value.clientHeight
  }

  function centerImage() {
    offsetX.value = (stageWidth.value - displayWidth.value) / 2
    offsetY.value = (stageHeight.value - displayHeight.value) / 2
  }

  function clampOffset() {
    const minX = stageWidth.value - displayWidth.value
    const minY = stageHeight.value - displayHeight.value
    offsetX.value = Math.min(0, Math.max(minX, offsetX.value))
    offsetY.value = Math.min(0, Math.max(minY, offsetY.value))
  }

  async function onImageLoad() {
    naturalWidth.value = image.value.naturalWidth
    naturalHeight.value = image.value.naturalHeight
    zoom.value = 1
    await nextTick()
    measureStage()
    centerImage()
  }

  // Re-anchors the zoom to the stage's center rather than the image's
  // top-left corner: whatever content point is currently centered in the
  // frame stays centered as the scale changes.
  watch(zoom, (newZoom, oldZoom) => {
    if (!stageWidth.value) return

    const oldScale = baseScale.value * oldZoom
    const newScale = baseScale.value * newZoom
    const centerX = stageWidth.value / 2
    const centerY = stageHeight.value / 2
    const contentX = (centerX - offsetX.value) / oldScale
    const contentY = (centerY - offsetY.value) / oldScale

    offsetX.value = centerX - contentX * newScale
    offsetY.value = centerY - contentY * newScale
    clampOffset()
  })

  // Plain `:ref="image"` bindings would work fine if only one <img> ever
  // used it, but the background/avatar adjust images are two mutually
  // exclusive v-if branches sharing this one ref - Vue still clears the
  // *non-rendered* branch's binding to null on every patch, which would
  // stomp the just-set element from the active branch. Guarding against
  // null here (a function-ref callback, not a plain ref) makes that
  // clear-out a no-op instead.
  function setImage(el) {
    if (el) image.value = el
  }

  let resizeObserver = null

  // Called from a template ref callback on whichever real element is
  // currently being adjusted (the card's background strip or avatar
  // circle) - it can change out from under this composable (switching from
  // one to the other, or a fresh pick replacing the same one), so the old
  // observer must be torn down before watching the new element.
  function setStage(el) {
    if (!el || stage.value === el) return

    resizeObserver?.disconnect()
    stage.value = el
    measureStage()
    clampOffset()

    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(() => {
        measureStage()
        clampOffset()
      })
      resizeObserver.observe(el)
    }
  }

  onBeforeUnmount(() => resizeObserver?.disconnect())

  const dragState = ref(null)

  function onDragStart(e) {
    dragState.value = {
      pointerId: e.pointerId,
      startX: e.clientX,
      startY: e.clientY,
      originX: offsetX.value,
      originY: offsetY.value,
    }
    e.currentTarget.setPointerCapture?.(e.pointerId)
  }

  function onDragMove(e) {
    if (!dragState.value || dragState.value.pointerId !== e.pointerId) return
    offsetX.value = dragState.value.originX + (e.clientX - dragState.value.startX)
    offsetY.value = dragState.value.originY + (e.clientY - dragState.value.startY)
    clampOffset()
  }

  function onDragEnd() {
    dragState.value = null
  }

  // `fillColors`, when given, is a [from, to] pair of CSS color strings
  // painted as a top-left-to-bottom-right gradient before the image itself
  // is drawn - needed for any export that flattens transparency (a JPEG has
  // no alpha channel at all, and a canvas composites transparent pixels
  // against black when asked to encode one), so a transparent source image
  // shows the card's own gradient/color underneath instead of solid black.
  function exportDataUrl(outputWidth, mimeType, quality, fillColors) {
    const scale = effectiveScale.value
    const sourceX = -offsetX.value / scale
    const sourceY = -offsetY.value / scale
    const sourceW = stageWidth.value / scale
    const sourceH = stageHeight.value / scale

    const canvas = document.createElement('canvas')
    canvas.width = outputWidth
    canvas.height = Math.round(outputWidth * (stageHeight.value / stageWidth.value))
    const ctx = canvas.getContext('2d')

    if (fillColors) {
      const [from, to] = fillColors
      const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height)
      gradient.addColorStop(0, from)
      gradient.addColorStop(1, to)
      ctx.fillStyle = gradient
      ctx.fillRect(0, 0, canvas.width, canvas.height)
    }

    ctx.drawImage(image.value, sourceX, sourceY, sourceW, sourceH, 0, 0, canvas.width, canvas.height)
    return canvas.toDataURL(mimeType, quality)
  }

  return {
    stage,
    image,
    zoom,
    offsetX,
    offsetY,
    displayWidth,
    displayHeight,
    setStage,
    setImage,
    onImageLoad,
    onDragStart,
    onDragMove,
    onDragEnd,
    exportDataUrl,
  }
}
