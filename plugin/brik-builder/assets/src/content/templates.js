// Starter setups: a post type, its taxonomies and a field group, installed in one go.
import { __ } from '../builder/wp.js';
import { uid, slugify, CHOICE_TYPES, PARENT_TYPES } from './lib.js';

const choices = (...labels) => labels.map((l) => ({ value: slugify(l), label: l }));

// Compact field spec: [type, label, extra]
function f(type, label, extra = {}) {
  const { options = {}, sub, ...rest } = extra;
  const field = { key: uid('field'), name: rest.name || slugify(label), label, type, instructions: '', required: false, default: '', placeholder: '', width: 100, conditions: [], options: { ...options }, ...rest };
  if (CHOICE_TYPES.includes(type) && !field.options.choices) field.options.choices = [];
  if (PARENT_TYPES.includes(type)) field.options.sub_fields = (sub || []).map((s) => f(...s));
  return field;
}

function postType(key, singular, plural, icon, extra = {}) {
  return {
    key,
    active: true,
    version: 1,
    singular,
    plural,
    labels: {},
    description: '',
    icon,
    public: true,
    has_archive: true,
    archive_slug: '',
    rewrite_slug: slugify(plural, '-'),
    hierarchical: false,
    show_in_rest: true,
    show_in_menu: true,
    menu_position: 25,
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
    taxonomies: [],
    exclude_from_search: false,
    capability_type: 'post',
    brik: true,
    ...extra,
  };
}

function taxonomy(key, singular, plural, postTypes, extra = {}) {
  return { key, active: true, version: 1, singular, plural, labels: {}, description: '', hierarchical: true, public: true, show_in_rest: true, show_admin_column: true, rewrite_slug: slugify(singular, '-'), post_types: postTypes, ...extra };
}

function group(title, type, fields, extra = {}) {
  return { key: uid('group'), active: true, version: 1, title, location: [[{ param: 'post_type', operator: '==', value: type }]], position: 'normal', style: 'card', order: 0, fields, ...extra };
}

export const TEMPLATES = [
  {
    id: 'portfolio',
    name: __('Portfolio', 'brik-builder'),
    description: __('Projects with client, year, gallery and website, grouped by project type.', 'brik-builder'),
    icon: 'briefcase',
    color: 'bg-violet-500/10 text-violet-600',
    build: () => {
      const pt = postType('project', __('Project', 'brik-builder'), __('Projects', 'brik-builder'), 'dashicons-portfolio', { taxonomies: ['project_type'] });
      return {
        post_types: [pt],
        taxonomies: [taxonomy('project_type', __('Project type', 'brik-builder'), __('Project types', 'brik-builder'), ['project'])],
        groups: [
          group(__('Project details', 'brik-builder'), 'project', [
            f('text', __('Client', 'brik-builder'), { width: 50 }),
            f('number', __('Year', 'brik-builder'), { width: 50, options: { min: 1990, max: 2100 } }),
            f('url', __('Website', 'brik-builder'), { placeholder: 'https://' }),
            f('gallery', __('Gallery', 'brik-builder')),
          ]),
        ],
      };
    },
  },
  {
    id: 'team',
    name: __('Team', 'brik-builder'),
    description: __('Team members with role, photo and social links.', 'brik-builder'),
    icon: 'users',
    color: 'bg-sky-500/10 text-sky-600',
    build: () => ({
      post_types: [postType('member', __('Team member', 'brik-builder'), __('Team', 'brik-builder'), 'dashicons-groups', { has_archive: false, rewrite_slug: 'team', supports: ['title', 'editor', 'thumbnail', 'page-attributes'], taxonomies: ['department'] })],
      taxonomies: [taxonomy('department', __('Department', 'brik-builder'), __('Departments', 'brik-builder'), ['member'])],
      groups: [
        group(__('Member profile', 'brik-builder'), 'member', [
          f('text', __('Role', 'brik-builder'), { width: 50, placeholder: __('e.g. Lead designer', 'brik-builder') }),
          f('email', __('Email', 'brik-builder'), { width: 50 }),
          f('image', __('Photo', 'brik-builder'), { options: { preview_size: 'medium' } }),
          f('repeater', __('Social links', 'brik-builder'), {
            name: 'socials',
            options: { layout: 'table', button_label: __('Add link', 'brik-builder') },
            sub: [
              ['select', __('Network', 'brik-builder'), { width: 33, options: { choices: choices('LinkedIn', 'X', 'Instagram', 'GitHub', 'Dribbble', 'Website') } }],
              ['url', __('URL', 'brik-builder'), { width: 66 }],
            ],
          }),
        ]),
      ],
    }),
  },
  {
    id: 'events',
    name: __('Events', 'brik-builder'),
    description: __('Events with date, time, venue, map and a ticket link.', 'brik-builder'),
    icon: 'calendar-days',
    color: 'bg-rose-500/10 text-rose-600',
    build: () => ({
      post_types: [postType('event', __('Event', 'brik-builder'), __('Events', 'brik-builder'), 'dashicons-calendar-alt', { taxonomies: ['event_category'] })],
      taxonomies: [taxonomy('event_category', __('Event category', 'brik-builder'), __('Event categories', 'brik-builder'), ['event'])],
      groups: [
        group(__('Event details', 'brik-builder'), 'event', [
          f('date', __('Date', 'brik-builder'), { width: 33, required: true, options: { display_format: 'F j, Y', return_format: 'F j, Y' } }),
          f('time', __('Start time', 'brik-builder'), { width: 33 }),
          f('time', __('End time', 'brik-builder'), { width: 33 }),
          f('text', __('Venue', 'brik-builder'), { width: 50 }),
          f('link', __('Ticket link', 'brik-builder'), { width: 50 }),
          f('map', __('Location', 'brik-builder'), { name: 'map', options: { zoom: 14 } }),
        ]),
      ],
    }),
  },
  {
    id: 'products',
    name: __('Products', 'brik-builder'),
    description: __('A simple catalogue: price, SKU, feature list and gallery.', 'brik-builder'),
    icon: 'shopping-bag',
    color: 'bg-emerald-500/10 text-emerald-600',
    build: () => ({
      post_types: [postType('product_item', __('Product', 'brik-builder'), __('Products', 'brik-builder'), 'dashicons-cart', { rewrite_slug: 'products', taxonomies: ['product_category'] })],
      taxonomies: [taxonomy('product_category', __('Product category', 'brik-builder'), __('Product categories', 'brik-builder'), ['product_item'])],
      groups: [
        group(__('Product data', 'brik-builder'), 'product_item', [
          f('number', __('Price', 'brik-builder'), { width: 33, options: { min: 0, step: 0.01, prepend: '$' } }),
          f('number', __('Sale price', 'brik-builder'), { width: 33, options: { min: 0, step: 0.01, prepend: '$' } }),
          f('text', __('SKU', 'brik-builder'), { width: 33 }),
          f('repeater', __('Features', 'brik-builder'), { options: { layout: 'table', button_label: __('Add feature', 'brik-builder') }, sub: [['text', __('Feature', 'brik-builder')]] }),
          f('gallery', __('Gallery', 'brik-builder')),
        ]),
      ],
    }),
  },
  {
    id: 'testimonials',
    name: __('Testimonials', 'brik-builder'),
    description: __('Quotes with author, company, rating and photo.', 'brik-builder'),
    icon: 'quote',
    color: 'bg-amber-500/10 text-amber-600',
    build: () => ({
      post_types: [postType('testimonial', __('Testimonial', 'brik-builder'), __('Testimonials', 'brik-builder'), 'dashicons-format-quote', { public: false, has_archive: false, supports: ['title', 'editor'], brik: false })],
      taxonomies: [],
      groups: [
        group(__('Testimonial', 'brik-builder'), 'testimonial', [
          f('text', __('Author', 'brik-builder'), { width: 50, required: true }),
          f('text', __('Company', 'brik-builder'), { width: 50 }),
          f('range', __('Rating', 'brik-builder'), { default: 5, options: { min: 1, max: 5, step: 1 } }),
          f('image', __('Photo', 'brik-builder')),
        ]),
      ],
    }),
  },
  {
    id: 'faq',
    name: __('FAQ', 'brik-builder'),
    description: __('Questions and answers, sorted into topics.', 'brik-builder'),
    icon: 'circle-question-mark',
    color: 'bg-indigo-500/10 text-indigo-600',
    build: () => ({
      post_types: [postType('faq', __('FAQ', 'brik-builder'), __('FAQs', 'brik-builder'), 'dashicons-editor-help', { has_archive: true, rewrite_slug: 'faq', supports: ['title', 'page-attributes'], taxonomies: ['faq_topic'], brik: false })],
      taxonomies: [taxonomy('faq_topic', __('Topic', 'brik-builder'), __('Topics', 'brik-builder'), ['faq'])],
      groups: [group(__('Answer', 'brik-builder'), 'faq', [f('wysiwyg', __('Answer', 'brik-builder'), { required: true, options: { toolbar: 'basic', media: false } })], { position: 'after_title', style: 'seamless' })],
    }),
  },
  {
    id: 'real-estate',
    name: __('Real estate', 'brik-builder'),
    description: __('Property listings with price, specs, amenities, gallery and map.', 'brik-builder'),
    icon: 'house',
    color: 'bg-orange-500/10 text-orange-600',
    build: () => ({
      post_types: [postType('property', __('Property', 'brik-builder'), __('Properties', 'brik-builder'), 'dashicons-admin-home', { taxonomies: ['property_type', 'property_location'] })],
      taxonomies: [
        taxonomy('property_type', __('Property type', 'brik-builder'), __('Property types', 'brik-builder'), ['property']),
        taxonomy('property_location', __('Location', 'brik-builder'), __('Locations', 'brik-builder'), ['property']),
      ],
      groups: [
        group(__('Listing details', 'brik-builder'), 'property', [
          f('button_group', __('Status', 'brik-builder'), { name: 'listing_status', default: 'for_sale', options: { choices: choices('For sale', 'For rent', 'Sold') } }),
          f('number', __('Price', 'brik-builder'), { width: 50, options: { min: 0, prepend: '$' } }),
          f('number', __('Area', 'brik-builder'), { width: 50, options: { min: 0, append: 'm²' } }),
          f('number', __('Bedrooms', 'brik-builder'), { width: 33, options: { min: 0 } }),
          f('number', __('Bathrooms', 'brik-builder'), { width: 33, options: { min: 0 } }),
          f('number', __('Parking', 'brik-builder'), { width: 33, options: { min: 0 } }),
          f('checkbox', __('Amenities', 'brik-builder'), { options: { choices: choices('Pool', 'Garden', 'Garage', 'Air conditioning', 'Balcony', 'Elevator') } }),
          f('gallery', __('Photos', 'brik-builder')),
          f('map', __('Map', 'brik-builder')),
        ]),
      ],
    }),
  },
  {
    id: 'jobs',
    name: __('Jobs', 'brik-builder'),
    description: __('Job openings with type, salary range, location and apply link.', 'brik-builder'),
    icon: 'briefcase-business',
    color: 'bg-teal-500/10 text-teal-600',
    build: () => ({
      post_types: [postType('job', __('Job', 'brik-builder'), __('Jobs', 'brik-builder'), 'dashicons-id-alt', { rewrite_slug: 'careers', taxonomies: ['job_department'] })],
      taxonomies: [taxonomy('job_department', __('Department', 'brik-builder'), __('Departments', 'brik-builder'), ['job'])],
      groups: [
        group(__('Job details', 'brik-builder'), 'job', [
          f('select', __('Employment type', 'brik-builder'), { width: 50, options: { choices: choices('Full time', 'Part time', 'Contract', 'Internship') } }),
          f('text', __('Location', 'brik-builder'), { width: 50, placeholder: __('e.g. Remote', 'brik-builder') }),
          f('number', __('Salary from', 'brik-builder'), { width: 50, options: { min: 0, prepend: '$' } }),
          f('number', __('Salary to', 'brik-builder'), { width: 50, options: { min: 0, prepend: '$' } }),
          f('date', __('Closing date', 'brik-builder'), { width: 50 }),
          f('url', __('Apply link', 'brik-builder'), { width: 50 }),
        ]),
      ],
    }),
  },
];
