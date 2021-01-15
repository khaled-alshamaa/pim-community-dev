import React from 'react';
import {IllustrationProps} from './IllustrationProps';
import ProductPublished from '../../static/illustrations/ProductPublished.svg';

const ProductPublishedIllustration = ({title, size = 256, ...props}: IllustrationProps) => (
  <svg width={size} height={size} viewBox="0 0 256 256" {...props}>
    {title && <title>{title}</title>}
    <image href={ProductPublished} />
  </svg>
);

export {ProductPublishedIllustration};
