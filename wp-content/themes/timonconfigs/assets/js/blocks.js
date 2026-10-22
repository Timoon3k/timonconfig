/* Native Gutenberg section controls; no production Node runtime or build required. */
(() => {
  const { createElement: el, Fragment } = wp.element;
  const { InspectorControls, useBlockProps } = wp.blockEditor;
  const { PanelBody, SelectControl, TextControl, TextareaControl, Button, Notice } = wp.components;
  const ServerSideRender = wp.serverSideRender;
  const config = window.tcBlockEditor || { sections: {} };
  const benefits = [
    ['settings', 'Professionally Tuned Configs', 'Clear setup guidance for your supported device.'],
    ['crosshair', 'Setup with Confidence', 'Device, input, and version details in every listing.'],
    ['refresh', 'Regular Updates', 'Updates covered by your membership or license.'],
    ['users', 'Community & Support', 'Connect with the TimonConfigs Discord community.'],
    ['shield', 'Clear Access Terms', 'Free, Monthly, and Lifetime options with clear terms.'],
  ];
  wp.blocks.registerBlockType('timonconfigs/section', {
    title: 'TimonConfigs section', icon: 'games', category: 'design',
    description: 'A premium storefront section with shared WordPress content.',
    edit({ attributes, setAttributes }) {
      const section = attributes.section || 'hero';
      const options = Object.entries(config.sections).map(([value, label]) => ({ value, label }));
      const relatedUrl = ({ games: config.gamesUrl, videos: config.videosUrl, reviews: config.reviewsUrl, faq: config.faqUrl, pricing: config.customizeUrl })[section];
      const controls = [el(SelectControl, { key: 'section', label: 'Section', value: section, options, onChange: value => setAttributes({ section: value }) })];
      if (!['assurance', 'search'].includes(section)) controls.push(el(TextControl, { key: 'title', label: 'Heading', value: attributes.title || '', help: 'Leave empty to use the shared site heading.', onChange: title => setAttributes({ title }) }));
      if (['hero', 'cta'].includes(section)) {
        controls.push(el(TextareaControl, { key: 'description', label: 'Description', value: attributes.description || '', onChange: description => setAttributes({ description }) }));
        controls.push(el(TextControl, { key: 'primaryLabel', label: 'Primary button label', value: attributes.primaryLabel || '', onChange: primaryLabel => setAttributes({ primaryLabel }) }));
        controls.push(el(TextControl, { key: 'primaryUrl', label: 'Primary button destination', type: 'url', value: attributes.primaryUrl || '', onChange: primaryUrl => setAttributes({ primaryUrl }) }));
      }
      if (section === 'cta') controls.push(el(TextControl, { key: 'image', label: 'Background image URL', type: 'url', value: attributes.image || '', onChange: image => setAttributes({ image }) }));
      if (section === 'hero') {
        controls.push(el(TextControl, { key: 'eyebrow', label: 'Eyebrow', value: attributes.eyebrow || '', onChange: eyebrow => setAttributes({ eyebrow }) }));
        controls.push(el(TextControl, { key: 'tagline', label: 'Tagline', value: attributes.tagline || '', onChange: tagline => setAttributes({ tagline }) }));
        controls.push(el(TextControl, { key: 'image', label: 'Background image URL', type: 'url', value: attributes.image || '', help: 'Use a Media Library URL. Leave empty to use the shared Hero.', onChange: image => setAttributes({ image }) }));
      }
      if (section === 'benefits') {
        (attributes.items?.length ? attributes.items : benefits).forEach((item, index) => {
          const update = (position, value) => {
            const items = (attributes.items?.length ? attributes.items : benefits).map(entry => [...entry]);
            items[index][position] = value;
            setAttributes({ items });
          };
          controls.push(el(TextControl, { key: `benefit-${index}`, label: `Benefit ${index + 1}`, value: item[1], onChange: value => update(1, value) }));
          controls.push(el(TextareaControl, { key: `benefit-text-${index}`, label: `Benefit ${index + 1} description`, value: item[2], onChange: value => update(2, value) }));
        });
      }
      controls.push(el(Button, { key: 'shared', variant: 'link', href: relatedUrl || config.customizeUrl, target: '_blank', rel: 'noopener noreferrer' }, relatedUrl ? 'Edit shared section content' : 'Edit global links and appearance'));
      return el(Fragment, null,
        el(InspectorControls, null, el(PanelBody, { title: 'TimonConfigs content', initialOpen: true }, controls)),
        el('div', useBlockProps({ className: 'tc-block-editor-preview' }),
          el(Notice, { status: 'info', isDismissible: false }, `${config.sections[section] || section} — select this section to edit its settings. Shared cards are managed in their WordPress menus.`),
          el(ServerSideRender, { block: 'timonconfigs/section', attributes, httpMethod: 'POST' })
        )
      );
    },
    save() { return null; },
  });
})();
