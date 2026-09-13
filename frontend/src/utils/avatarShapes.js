// Mirrors User::AVATAR_SHAPES (app/src/Model/User.php) - keep both in sync.
// Every shape carries two equivalent descriptions of the same outline:
//   - borderRadius/clipPath: cheap, GPU-friendly CSS shaping for small,
//     filter-free consumers - the shape-picker swatches (DesignPickerModal,
//     CardImageAdjustPanel) and the profile-carousel mini card avatars
//     (ProfileCarousel.scss's --mini-card-avatar-radius/-clip-path).
//   - path: the same outline as an SVG path (on a 0-100 viewBox, since the
//     avatar is always 1:1), used by avatarShapeMaskImage() below for the
//     main profile card's avatar. That one needs a *mask* rather than
//     border-radius/clip-path because it also has to cast a drop-shadow
//     shaped like the avatar outline - see ProfileCard.scss's
//     .card-avatar-shadow-fill for why: clip-path/mask both clip away a
//     *filter's* rendered output on the element they're applied to (unlike
//     overflow: hidden, which only clips content), which is exactly what
//     silently killed the hexagon avatar's shadow before border-radius and
//     clip-path were unified onto one mask-image mechanism. Keeping the mask
//     on a separate, unfiltered element and blurring an *ancestor* instead
//     is what lets a shadow follow this same outline for every shape,
//     including a fully transparent profile picture (the shadow comes from
//     the mask's opaque silhouette, not the image's own alpha channel).
export const AVATAR_SHAPES = [
  {
    key: 'circle',
    borderRadius: '50%',
    clipPath: 'none',
    path: 'M50,0 A50,50 0 1,0 50,100 A50,50 0 1,0 50,0 Z',
  },
  {
    key: 'rounded-square',
    borderRadius: '18%',
    clipPath: 'none',
    path: 'M18,0 H82 A18,18 0 0 1 100,18 V82 A18,18 0 0 1 82,100 H18 A18,18 0 0 1 0,82 V18 A18,18 0 0 1 18,0 Z',
  },
  {
    key: 'square',
    borderRadius: '0',
    clipPath: 'none',
    path: 'M0,0 H100 V100 H0 Z',
  },
  {
    key: 'hexagon',
    borderRadius: '0',
    clipPath: 'polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)',
    path: 'M25,0 L75,0 L100,50 L75,100 L25,100 L0,50 Z',
  },
  {
    key: 'heart',
    borderRadius: '0',
    // A straight-line approximation of the same curve as `path` below -
    // clip-path: polygon() can't do curves, but its percentages (unlike
    // clip-path: path(), which takes fixed pixel coordinates) scale with
    // whatever box it's applied to, same as border-radius/clip-path: none
    // do for the other shapes here.
    clipPath:
      'polygon(50% 15%, 61% 4%, 73% 0%, 86% 3%, 95% 11%, 99% 24%, 97% 38%, 90% 50%, 50% 90%, 10% 50%, 3% 38%, 1% 24%, 5% 11%, 14% 3%, 27% 0%, 39% 4%)',
    path: 'M50,90 C50,90 10,65 10,35 C10,15 25,5 40,5 C48,5 50,12 50,15 C50,12 52,5 60,5 C75,5 90,15 90,35 C90,65 50,90 50,90 Z',
  },
]

export const DEFAULT_AVATAR_SHAPE = 'circle'

// Shared by DesignPickerModal (the shape picker in the design modal) and
// CardImageAdjustPanel (the same picker, but shown inline while uploading a
// new avatar) so both present identical labels for the same key.
export const AVATAR_SHAPE_LABEL_KEYS = {
  circle: 'profile.designShapeCircle',
  'rounded-square': 'profile.designShapeRoundedSquare',
  square: 'profile.designShapeSquare',
  hexagon: 'profile.designShapeHexagon',
  heart: 'profile.designShapeHeart',
}

function findShape(shapeKey) {
  return AVATAR_SHAPES.find((s) => s.key === shapeKey) ?? AVATAR_SHAPES[0]
}

export function avatarShapeStyle(shapeKey) {
  const shape = findShape(shapeKey)
  return { borderRadius: shape.borderRadius, clipPath: shape.clipPath }
}

// A CSS mask-image value, opaque (white) exactly inside the shape's outline
// and transparent everywhere else on a 100x100 box - applied identically to
// the main profile card's avatar image *and* its separate shadow silhouette
// (see ProfileCard.scss), so both always agree on the current shape without
// any per-shape special-casing.
export function avatarShapeMaskImage(shapeKey) {
  const shape = findShape(shapeKey)
  const svg =
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>` +
    `<path fill='white' d='${shape.path}'/>` +
    `</svg>`

  return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`
}
