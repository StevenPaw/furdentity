// Mirrors User::AVATAR_SHAPES (app/src/Model/User.php) - keep both in sync.
// Every shape carries three equivalent descriptions of the same outline,
// because each consumer needs a different CSS mechanism:
//   - borderRadius/clipPath: the profile card face (ProfileCard.scss's
//     --card-avatar-radius/--card-avatar-clip-path) and the DesignPickerModal
//     swatches - plain image, no vignette involved, so clip-path is fine.
//   - maskHolePath: the ImageCropModal's dimmed-vignette overlay. That
//     overlay used to be a single box-shadow-spread trick relying on
//     border-radius, but box-shadow gets clipped away by clip-path (so it
//     can't produce the hexagon's vignette) - the mask-image "hole in an
//     opaque rect" technique below replaces it uniformly for every shape.
// All coordinates are percentages of a 0-100 box (the avatar is always
// 1:1), so they drop straight into an SVG viewBox="0 0 100 100".
export const AVATAR_SHAPES = [
  {
    key: 'circle',
    borderRadius: '50%',
    clipPath: 'none',
    maskHolePath: 'M50,0 A50,50 0 1,0 50,100 A50,50 0 1,0 50,0 Z',
  },
  {
    key: 'rounded-square',
    borderRadius: '18%',
    clipPath: 'none',
    maskHolePath:
      'M18,0 H82 A18,18 0 0 1 100,18 V82 A18,18 0 0 1 82,100 H18 A18,18 0 0 1 0,82 V18 A18,18 0 0 1 18,0 Z',
  },
  {
    key: 'square',
    borderRadius: '0',
    clipPath: 'none',
    maskHolePath: 'M0,0 H100 V100 H0 Z',
  },
  {
    key: 'hexagon',
    borderRadius: '0',
    clipPath: 'polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)',
    maskHolePath: 'M25,0 L75,0 L100,50 L75,100 L25,100 L0,50 Z',
  },
]

export const DEFAULT_AVATAR_SHAPE = 'circle'

// Shared by DesignPickerModal (the shape picker in the design modal) and
// ImageCropModal (the same picker, but shown inline while uploading a new
// avatar) so both present identical labels for the same key.
export const AVATAR_SHAPE_LABEL_KEYS = {
  circle: 'profile.designShapeCircle',
  'rounded-square': 'profile.designShapeRoundedSquare',
  square: 'profile.designShapeSquare',
  hexagon: 'profile.designShapeHexagon',
}

function findShape(shapeKey) {
  return AVATAR_SHAPES.find((s) => s.key === shapeKey) ?? AVATAR_SHAPES[0]
}

export function avatarShapeStyle(shapeKey) {
  const shape = findShape(shapeKey)
  return { borderRadius: shape.borderRadius, clipPath: shape.clipPath }
}

// A CSS mask-image value that's opaque (white) everywhere except a
// shape-sized hole, which is left untouched (transparent). Applied to an
// otherwise-opaque dark overlay, this "cuts a hole" of exactly that shape -
// fill='white' rather than the more common 'black' so it reads correctly
// whichever mask mode (alpha vs. luminance) a browser defaults to for a raw
// SVG mask-image, since undrawn (hole) pixels are both alpha:0 and
// luminance:0 either way.
export function avatarShapeMaskImage(shapeKey) {
  const shape = findShape(shapeKey)
  const svg =
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>` +
    `<path fill-rule='evenodd' fill='white' d='M0,0 H100 V100 H0 Z ${shape.maskHolePath}'/>` +
    `</svg>`

  return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`
}
