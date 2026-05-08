/**
 * tokens.js — Module 1: Design Token System
 * JavaScript mirror of tokens.css for component use.
 * Spec Part 17.3: "exported as CSS custom properties at :root level
 * and as a JavaScript/TypeScript token object for component use."
 *
 * Usage (read live CSS var):
 *   DesignTokens.get('color.brand.500')  → '#2563EB'
 *
 * Usage (static object):
 *   DesignTokens.color.brand[500]        → '#2563EB'
 *   DesignTokens.space[4]                → '16px'
 *   DesignTokens.radius.lg               → '8px'
 */

'use strict';

window.DesignTokens = (function () {

  /* ----------------------------------------------------------
     STATIC TOKEN OBJECT
     Mirrors tokens.css. Update both files together.
     ---------------------------------------------------------- */

  var tokens = {

    /* ── Color ─────────────────────────────────────────────── */
    color: {

      white:    '#FFFFFF',

      bg: {
        page:     '#F8FAFC',
        elevated: '#FFFFFF',
        subtle:   '#F0F4FA',
        muted:    '#F8FAFC'
      },

      border: {
        default: '#E6EEF8',
        hover:   '#BFDBFE',
        focus:   '#2563EB'
      },

      text: {
        primary:     '#1A2B4A',
        body:        '#374151',
        secondary:   '#6B7A99',
        placeholder: '#9AA5B8',
        meta:        '#9AA5B8',
        disabled:    '#9AA5B8'
      },

      brand: {
        50:  '#EFF6FF',
        100: '#DBEAFE',
        200: '#BFDBFE',
        500: '#2563EB',
        600: '#1D4ED8',
        700: '#1E40AF'
      },

      success: {
        text:  '#16A34A',
        bg:    '#DCFCE7',
        solid: '#16A34A'
      },

      warning: {
        text:   '#D97706',
        bg:     '#FFFBEB',
        border: '#D97706'
      },

      danger: {
        text:   '#DC2626',
        bg:     '#FEF2F2',
        border: '#FECACA'
      },

      orange: {
        text:  '#EA580C',
        bg:    '#FFF7ED',
        solid: '#EA580C'
      },

      purple: {
        text: '#7C3AED',
        bg:   '#F5F3FF'
      },

      teal: {
        text: '#0D9488',
        bg:   '#ECFEFF'
      },

      slate: {
        text: '#64748B',
        bg:   '#F1F5F9'
      },

      skeleton: {
        base:  '#E9EEF6',
        shine: '#F4F7FC'
      },

      surface: {
        hover:    '#F8FAFC',
        hoverNav: '#F0F4FA',
        active:   '#EFF6FF'
      }
    },

    /* ── Typography ─────────────────────────────────────────── */
    font: {

      family: {
        base:  "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif",
        print: 'Arial, Helvetica, sans-serif'
      },

      size: {
        10: '10px',
        11: '11px',
        12: '12px',
        13: '13px',
        14: '14px',
        15: '15px',
        16: '16px',
        18: '18px',
        22: '22px'
      },

      weight: {
        regular:  400,
        medium:   500,
        semibold: 600,
        bold:     700
      },

      lineHeight: {
        14: '14px',
        16: '16px',
        18: '18px',
        20: '20px',
        22: '22px',
        24: '24px',
        30: '30px'
      },

      letterSpacing: {
        tight: '0',
        label: '0.8px',
        micro: '1px'
      }
    },

    /* ── Type Scale Bundles ──────────────────────────────────── */
    type: {
      pageHeading:       { size: '22px', weight: 700, lineHeight: '30px', color: '#1A2B4A' },
      sectionHeading:    { size: '16px', weight: 600, lineHeight: '24px', color: '#1A2B4A' },
      subsectionHeading: { size: '15px', weight: 600, lineHeight: '22px', color: '#1A2B4A' },
      body:              { size: '14px', weight: 400, lineHeight: '20px', color: '#374151' },
      bodyMedium:        { size: '14px', weight: 500, lineHeight: '20px', color: '#374151' },
      cardLabel:         { size: '13px', weight: 500, lineHeight: '18px', color: '#6B7A99' },
      meta:              { size: '12px', weight: 400, lineHeight: '16px', color: '#9AA5B8' },
      metaMedium:        { size: '12px', weight: 500, lineHeight: '16px', color: '#6B7A99' },
      badge:             { size: '11px', weight: 600, lineHeight: '14px', color: 'varies'  },
      timelineLabel:     { size: '11px', weight: 700, lineHeight: '14px', letterSpacing: '0.8px', color: 'semantic' },
      tableHeader:       { size: '12px', weight: 600, lineHeight: '16px', color: '#6B7A99' },
      microHeading:      { size: '10px', weight: 700, lineHeight: '14px', letterSpacing: '1px', color: '#1A2B4A' }
    },

    /* ── Spacing (8px grid, multiples of 4) ─────────────────── */
    space: {
      0:  '0px',
      1:  '4px',
      2:  '8px',
      3:  '12px',
      4:  '16px',
      5:  '20px',
      6:  '24px',
      7:  '28px',
      8:  '32px',
      9:  '36px',
      10: '40px',
      12: '48px',
      14: '56px',
      16: '64px',
      20: '80px',
      24: '96px',
      30: '120px'
    },

    /* ── Component Sizes ─────────────────────────────────────── */
    size: {
      btnSm:          '34px',
      btn:            '36px',
      input:          '36px',
      textareaMin:    '80px',
      navItem:        '40px',
      topbar:         '64px',
      sidebar:        '240px',
      iconHitMin:     '32px',   /* spec 15.2 */
      avatarSm:       '36px',
      avatarLg:       '48px',
      statIconBox:    '36px',
      quickLinkBox:   '32px',
      timelineNode:   '32px',
      moaBadge:       '32px',
      paginationItem: '32px'
    },

    /* ── Border Radius ──────────────────────────────────────── */
    radius: {
      none: '0',
      sm:   '4px',
      md:   '6px',
      lg:   '8px',
      xl:   '12px',
      full: '9999px'
    },

    /* ── Shadows ─────────────────────────────────────────────── */
    shadow: {
      none: 'none',
      sm:   '0 1px 3px rgba(0, 0, 0, 0.06)',
      md:   '0 2px 8px rgba(0, 0, 0, 0.06)',
      lg:   '0 8px 24px rgba(0, 0, 0, 0.08)'
    },

    /* ── Z-Index ─────────────────────────────────────────────── */
    z: {
      content:    1,
      breadcrumb: 1,
      topbar:     20,
      sidebar:    20,
      dropdown:   30,
      datepicker: 35,
      modal:      40,
      toast:      50
    },

    /* ── Transitions ─────────────────────────────────────────── */
    transition: {
      fast:     '80ms ease',
      default:  '120ms ease-out',
      hover:    '80ms ease',
      focus:    '80ms ease',
      dropdown: '120ms ease-out'
    },

    /* ── Icon ────────────────────────────────────────────────── */
    icon: {
      stroke:   1.75,
      size: {
        xs:       '14px',
        sm:       '16px',
        md:       '18px',
        lg:       '20px',
        xl:       '22px',
        stat:     '18px',
        quickLink:'16px',
        timeline: '16px'
      }
    },

    /* ── Focus Ring ──────────────────────────────────────────── */
    focus: {
      width:  '3px',
      color:  'rgba(37, 99, 235, 0.5)',
      offset: '2px'
    },

    /* ── Layout ──────────────────────────────────────────────── */
    layout: {
      sidebarWidth:         '240px',
      topbarHeight:         '64px',
      breadcrumbHeight:     '32px',
      contentMaxWidth:      '1280px',
      contentPaddingX:      '24px',
      contentPaddingTop:    '20px',
      contentPaddingBottom: '24px'
    }
  };

  /* ----------------------------------------------------------
     LIVE CSS VARIABLE READER
     Reads the computed value of a CSS custom property from :root.
     Useful when tokens can be overridden at runtime.
     Usage: DesignTokens.getCssVar('--color-brand-500')
     ---------------------------------------------------------- */
  function getCssVar(varName) {
    return getComputedStyle(document.documentElement)
      .getPropertyValue(varName)
      .trim();
  }

  /* ----------------------------------------------------------
     DOT-PATH GETTER
     Traverses the static token object by dot-separated path.
     Usage: DesignTokens.get('color.brand.500') → '#2563EB'
            DesignTokens.get('space.4')          → '16px'
     ---------------------------------------------------------- */
  function get(path) {
    return path.split('.').reduce(function (obj, key) {
      return obj != null ? obj[key] : undefined;
    }, tokens);
  }

  return {
    /* Static token object — direct property access */
    color:      tokens.color,
    font:       tokens.font,
    type:       tokens.type,
    space:      tokens.space,
    size:       tokens.size,
    radius:     tokens.radius,
    shadow:     tokens.shadow,
    z:          tokens.z,
    transition: tokens.transition,
    icon:       tokens.icon,
    focus:      tokens.focus,
    layout:     tokens.layout,

    /* Utility methods */
    get:        get,
    getCssVar:  getCssVar
  };

}());
